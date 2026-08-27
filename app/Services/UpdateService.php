<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use FilamentManager\Core\Migrator;
use RuntimeException;
use ZipArchive;

final class UpdateService
{
    public function __construct(private readonly App $app) {}
    public function currentVersion(): string{return ltrim(trim((string)file_get_contents(FM_ROOT.'/VERSION')),'v');}

    public function check(bool $force=false): array
    {
        $cache=FM_ROOT.'/storage/cache/update.json';
        if(!$force&&is_file($cache)&&filemtime($cache)>time()-(int)$this->app->config('update_check_interval',21600)){$cached=json_decode((string)file_get_contents($cache),true);if(is_array($cached))return $cached;}
        $repo=(string)$this->app->config('github_repository');$releases=$this->httpJson('https://api.github.com/repos/'.$repo.'/releases?per_page=1');
        if ($releases === []) {
            $result=['checkedAt'=>gmdate('c'),'current'=>$this->currentVersion(),'latest'=>$this->currentVersion(),'available'=>false,'published'=>false,'releaseUrl'=>'','notes'=>'No published GitHub releases are available.','assets'=>[]];
            $this->writeCache($cache,$result);return $result;
        }
        $release=$releases[0]??null;if(!is_array($release))throw new RuntimeException('GitHub returned an invalid releases response.');$tag=ltrim((string)($release['tag_name']??''),'v');if(!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',$tag))throw new RuntimeException('GitHub returned an invalid release version.');
        $assets=[];foreach($release['assets']??[] as $asset)$assets[(string)$asset['name']]=(string)$asset['browser_download_url'];
        $result=['checkedAt'=>gmdate('c'),'current'=>$this->currentVersion(),'latest'=>$tag,'available'=>version_compare($tag,$this->currentVersion(),'>'),'published'=>true,'releaseUrl'=>(string)($release['html_url']??''),'notes'=>(string)($release['body']??''),'assets'=>$assets];
        $this->writeCache($cache,$result);return $result;
    }

    public function install(string $expectedVersion): array
    {
        $release=$this->check(true);if(!$release['available']||$release['latest']!==$expectedVersion)throw new RuntimeException('The selected update is no longer available.');
        $zipName='filamentmanager-server-'.$expectedVersion.'.zip';$shaName=$zipName.'.sha256';if(empty($release['assets'][$zipName])||empty($release['assets'][$shaName]))throw new RuntimeException('Release package or checksum is missing.');
        $backupService=new BackupService($this->app);$backupService->create('pre-update-'.$expectedVersion);$filesBackup=$backupService->createApplicationFiles('pre-update-'.$expectedVersion);
        $stage=FM_ROOT.'/storage/update-staging';if(is_dir($stage))$this->removeTree($stage);mkdir($stage,0750,true);
        $zipPath=$stage.'/'.$zipName;$this->download($release['assets'][$zipName],$zipPath);$expectedHash=trim($this->httpText($release['assets'][$shaName]));$expectedHash=preg_split('/\s+/', $expectedHash)[0]??'';
        if(!preg_match('/^[a-f0-9]{64}$/i',$expectedHash)||!hash_equals(strtolower($expectedHash),hash_file('sha256',$zipPath)))throw new RuntimeException('Update checksum verification failed.');
        $archive=new ZipArchive();if($archive->open($zipPath)!==true)throw new RuntimeException('Cannot open update archive.');$extract=$stage.'/package';mkdir($extract,0750,true);
        for($i=0;$i<$archive->numFiles;$i++){ $name=$archive->getNameIndex($i);if($name===false||str_contains($name,'..')||str_starts_with($name,'/')||str_contains($name,'\\'))throw new RuntimeException('Unsafe path in update archive.'); }
        if(!$archive->extractTo($extract)){ $archive->close();throw new RuntimeException('Cannot extract update archive.');}$archive->close();
        $root=$extract;if(!is_file($root.'/VERSION')){$children=glob($extract.'/*',GLOB_ONLYDIR)?:[];if(count($children)===1)$root=$children[0];}
        if(trim((string)@file_get_contents($root.'/VERSION'))!==$expectedVersion)throw new RuntimeException('Update package version does not match.');
        file_put_contents(FM_ROOT.'/storage/maintenance.lock',gmdate('c'),LOCK_EX);
        try{$this->copyRelease($root,FM_ROOT);Migrator::run($this->app->db()->pdo());}catch(\Throwable $updateError){try{$this->restoreApplicationFiles($filesBackup,$stage.'/rollback');}catch(\Throwable $rollbackError){throw new RuntimeException('Update failed and automatic file rollback also failed: '.$rollbackError->getMessage(),0,$updateError);}throw $updateError;}finally{@unlink(FM_ROOT.'/storage/maintenance.lock');$this->removeTree($stage);}
        return ['version'=>$expectedVersion];
    }

    private function copyRelease(string $source,string $destination):void
    {
        $protected=['config/local.php','storage','install','public/install'];$iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST);
        foreach($iterator as $item){$relative=str_replace('\\','/',substr($item->getPathname(),strlen($source)+1));foreach($protected as $skip)if($relative===$skip||str_starts_with($relative,$skip.'/'))continue 2;$target=$destination.'/'.$relative;if($item->isDir()){if(!is_dir($target))mkdir($target,0755,true);}else{if(!is_dir(dirname($target)))mkdir(dirname($target),0755,true);$tmp=$target.'.update';if(!copy($item->getPathname(),$tmp)||!rename($tmp,$target))throw new RuntimeException('Cannot update '.$relative);}}
    }
    private function restoreApplicationFiles(string $archivePath,string $destination):void
    {
        $archive=new ZipArchive();if($archive->open($archivePath)!==true)throw new RuntimeException('Cannot open file rollback archive.');if(!is_dir($destination))mkdir($destination,0750,true);if(!$archive->extractTo($destination)){$archive->close();throw new RuntimeException('Cannot extract file rollback archive.');}$archive->close();$this->copyRelease($destination,FM_ROOT);
    }
    private function writeCache(string $path,array $result):void{if(file_put_contents($path,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Cannot write the update cache. Check storage/cache permissions.');}
    private function httpJson(string $url):array{$data=json_decode($this->httpText($url),true);if(!is_array($data))throw new RuntimeException('GitHub returned invalid JSON.');return $data;}
    private function httpText(string $url):string
    {
        $headers=["User-Agent: FilamentManager-Server/{$this->currentVersion()}",'Accept: application/vnd.github+json','X-GitHub-Api-Version: 2022-11-28'];
        if(function_exists('curl_init')){
            $curl=curl_init($url);if($curl===false)throw new RuntimeException('Cannot initialize cURL.');
            curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>$headers]);
            $data=curl_exec($curl);$error=curl_error($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
            if(!is_string($data))throw new RuntimeException('Cannot contact GitHub via cURL: '.($error?:'unknown transport error'));
            if($status<200||$status>=300)throw new RuntimeException("GitHub returned HTTP {$status}.");
            return $data;
        }
        if(!filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOL))throw new RuntimeException('Update checks require the PHP cURL extension or allow_url_fopen.');
        $ctx=stream_context_create(['http'=>['timeout'=>30,'ignore_errors'=>true,'header'=>implode("\r\n",$headers)."\r\n"]]);$data=@file_get_contents($url,false,$ctx);
        $status=0;foreach($http_response_header??[] as $header)if(preg_match('/^HTTP\/\S+\s+(\d{3})/',$header,$match))$status=(int)$match[1];
        if(!is_string($data))throw new RuntimeException('Cannot contact GitHub. Check outbound HTTPS access and the server CA certificate bundle.');
        if($status<200||$status>=300)throw new RuntimeException("GitHub returned HTTP {$status}.");
        return $data;
    }
    private function download(string $url,string $target):void{$data=$this->httpText($url);if(file_put_contents($target,$data,LOCK_EX)===false)throw new RuntimeException('Cannot save update package.');}
    private function removeTree(string $path):void{if(!is_dir($path))return;$items=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($items as $item){$item->isDir()?@rmdir($item->getPathname()):@unlink($item->getPathname());}@rmdir($path);}
}
