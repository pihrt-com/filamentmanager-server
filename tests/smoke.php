<?php

declare(strict_types=1);

define('FM_ROOT', dirname(__DIR__));
spl_autoload_register(static function(string $class):void{$prefix='FilamentManager\\';if(str_starts_with($class,$prefix)){$path=FM_ROOT.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($path))require $path;}});

$uuid=FilamentManager\Core\Uuid::v4();
if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',$uuid))throw new RuntimeException('UUID v4 generation failed.');
$migration=require FM_ROOT.'/database/migrations/001_initial.php';
if(count($migration)<15)throw new RuntimeException('Initial schema is unexpectedly incomplete.');
$required=['README.md','CHANGELOG.md','SECURITY.md','prepare-install.php','public/index.php','install/index.php','routes/web.php','routes/api.php'];
foreach($required as $file)if(!is_file(FM_ROOT.'/'.$file))throw new RuntimeException('Missing '.$file);
$translations=[];
foreach(['cs','en'] as $locale){$translations[$locale]=require FM_ROOT.'/resources/lang/'.$locale.'/messages.php';}
$usedKeys=[];
foreach(glob(FM_ROOT.'/resources/views/*.php')?:[] as $view){$source=(string)file_get_contents($view);preg_match_all("/View::t\\('([^']+)'/",$source,$matches);$usedKeys=array_merge($usedKeys,$matches[1]);if(str_contains($source,'style=')||str_contains($source,'<script'))throw new RuntimeException('Strict CSP violation in '.basename($view));}
foreach(array_unique($usedKeys) as $key)foreach($translations as $locale=>$messages)if(!array_key_exists($key,$messages))throw new RuntimeException("Missing {$locale} translation: {$key}");
echo "Smoke tests passed.\n";
