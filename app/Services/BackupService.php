<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use RuntimeException;
use ZipArchive;

final class BackupService
{
    private const TABLES = ['workspaces','users','devices','manufacturers','materials','locations','spools','printers','printer_slots','spool_movements','settings','sync_changes','audit_log'];
    public function __construct(private readonly App $app) {}

    public function create(string $reason = 'manual'): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP extension is required.');
        $timestamp = gmdate('Ymd-His');
        $filename = "filamentmanager-backup-{$timestamp}.zip";
        $path = FM_ROOT . '/storage/backups/' . $filename;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create backup archive.');
        $manifest = ['format'=>'filamentmanager-backup','formatVersion'=>1,'serverVersion'=>trim((string)file_get_contents(FM_ROOT.'/VERSION')),'createdAt'=>gmdate('c'),'reason'=>$reason,'tables'=>self::TABLES];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        foreach (self::TABLES as $table) {
            $rows = $this->app->db()->fetchAll('SELECT * FROM `' . $table . '`');
            $zip->addFromString('database/' . $table . '.json', json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        }
        $zip->close();
        @chmod($path, 0640);
        $this->prune((int)$this->app->config('backup_retention',10));
        return $path;
    }

    public function restore(string $path): void
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP extension is required.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Cannot open backup archive.');
        $rawManifest = $zip->getFromName('manifest.json');
        $manifest = is_string($rawManifest) ? json_decode($rawManifest,true) : null;
        if (!is_array($manifest) || ($manifest['format']??'')!=='filamentmanager-backup' || (int)($manifest['formatVersion']??0)!==1) { $zip->close(); throw new RuntimeException('Unsupported or damaged backup.'); }
        $data=[];
        foreach(self::TABLES as $table){$raw=$zip->getFromName('database/'.$table.'.json');if(!is_string($raw)){ $zip->close();throw new RuntimeException('Backup is missing table '.$table);}$rows=json_decode($raw,true);if(!is_array($rows)){ $zip->close();throw new RuntimeException('Invalid data for '.$table);}$data[$table]=$rows;}
        $zip->close();
        $pdo=$this->app->db()->pdo();
        $pdo->beginTransaction();
        try{
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach(array_reverse(self::TABLES) as $table)$pdo->exec('DELETE FROM `'.$table.'`');
            foreach(self::TABLES as $table)foreach($data[$table] as $row){if(!$row)continue;$columns=array_keys($row);$sql='INSERT INTO `'.$table.'` (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';$stmt=$pdo->prepare($sql);$stmt->execute(array_values($row));}
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
        }catch(\Throwable $e){try{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}catch(\Throwable){}if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function createApplicationFiles(string $reason = 'pre-update'): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP extension is required.');
        $path = FM_ROOT . '/storage/backups/filamentmanager-files-' . gmdate('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create application backup.');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(FM_ROOT, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(FM_ROOT) + 1));
            if ($relative === 'config/local.php' || str_starts_with($relative, 'storage/') || str_starts_with($relative, '.git/') || str_starts_with($relative, 'dist/')) continue;
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();
        @chmod($path, 0640);
        return $path;
    }

    public function list(): array
    {
        $files=array_merge(glob(FM_ROOT.'/storage/backups/filamentmanager-backup-*.zip')?:[],glob(FM_ROOT.'/storage/backups/filamentmanager-files-*.zip')?:[]);rsort($files,SORT_STRING);return array_map(static fn($p)=>['name'=>basename($p),'size'=>filesize($p),'createdAt'=>gmdate('c',filemtime($p))],$files);
    }

    private function prune(int $keep): void
    {
        $files=glob(FM_ROOT.'/storage/backups/filamentmanager-backup-*.zip')?:[];rsort($files,SORT_STRING);foreach(array_slice($files,max(1,$keep)) as $file)@unlink($file);
    }
}
