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
$translationSources=array_merge(glob(FM_ROOT.'/resources/views/*.php')?:[],glob(FM_ROOT.'/app/Controllers/*.php')?:[]);
foreach($translationSources as $sourceFile){$source=(string)file_get_contents($sourceFile);preg_match_all("/View::t\\('([^']+)'/",$source,$matches);$usedKeys=array_merge($usedKeys,$matches[1]);if(str_contains($sourceFile,'resources/views')&&(str_contains($source,'style=')||str_contains($source,'<script')))throw new RuntimeException('Strict CSP violation in '.basename($sourceFile));}
foreach(array_unique($usedKeys) as $key)foreach($translations as $locale=>$messages)if(!array_key_exists($key,$messages))throw new RuntimeException("Missing {$locale} translation: {$key}");
$dynamicKeys=['role_admin','role_manager','role_operator','role_viewer','spool_status_in_stock','spool_status_loaded','spool_status_empty','spool_status_archived'];
foreach($dynamicKeys as $key)foreach($translations as $locale=>$messages)if(!array_key_exists($key,$messages))throw new RuntimeException("Missing {$locale} dynamic translation: {$key}");
$webRoutes=(string)file_get_contents(FM_ROOT.'/routes/web.php');
foreach(['/materials/{id}/edit','/materials/{id}','/locations/{id}/edit','/locations/{id}','/admin/users/{id}/edit','/admin/users/{id}','/admin/users/{id}/delete'] as $route)if(!str_contains($webRoutes,$route))throw new RuntimeException('Missing web route '.$route);
$locationController=(string)file_get_contents(FM_ROOT.'/app/Controllers/LocationController.php');
if(!str_contains($locationController,'$id!==null&&$parent===$id'))throw new RuntimeException('New locations must not trigger the self-parent check.');
$spoolController=(string)file_get_contents(FM_ROOT.'/app/Controllers/SpoolController.php');
$spoolForm=(string)file_get_contents(FM_ROOT.'/resources/views/spool_form.php');
if(!str_contains($spoolController,'location_id')||!str_contains($spoolForm,'name="location_id"'))throw new RuntimeException('Spool storage-location assignment is missing.');
$settingsView=(string)file_get_contents(FM_ROOT.'/resources/views/settings.php');
$dashboardView=(string)file_get_contents(FM_ROOT.'/resources/views/dashboard.php');
if(!str_contains($settingsView,"update['commits']"))throw new RuntimeException('Update commit overview is missing.');
if(!str_contains($webRoutes,'/admin/settings/backup/delete'))throw new RuntimeException('Backup delete route is missing.');
if(!str_contains($dashboardView,"update['available']"))throw new RuntimeException('Dashboard update notification is missing.');
$authController=(string)file_get_contents(FM_ROOT.'/app/Controllers/AuthController.php');
$dashboardController=(string)file_get_contents(FM_ROOT.'/app/Controllers/DashboardController.php');
if(!str_contains($authController,"Session::put('check_updates_after_login', true)"))throw new RuntimeException('Post-login update check trigger is missing.');
if(!str_contains($dashboardController,'$updates->check($force)')||!str_contains($dashboardView,'update-banner'))throw new RuntimeException('Immediate dashboard update banner is missing.');
$materialController=(string)file_get_contents(FM_ROOT.'/app/Controllers/MaterialController.php');
$materialsView=(string)file_get_contents(FM_ROOT.'/resources/views/materials.php');
$locationController=(string)file_get_contents(FM_ROOT.'/app/Controllers/LocationController.php');
$syncService=(string)file_get_contents(FM_ROOT.'/app/Services/SyncService.php');
$backupService=(string)file_get_contents(FM_ROOT.'/app/Services/BackupService.php');
foreach(['material_type','manufacturer','color'] as $filter)if(!str_contains($materialsView,'name="'.$filter.'"'))throw new RuntimeException('Missing material filter '.$filter);
if(!str_contains($materialController,"requireRole('admin','manager')")||!str_contains($locationController,"requireRole('admin','manager')"))throw new RuntimeException('Manager write endpoints must enforce roles.');
if(!str_contains($syncService,"if(\$user['role']==='viewer')throw new HttpException('Permission denied',403)"))throw new RuntimeException('Viewer API mutations must be denied.');
if(!str_contains($syncService,"\$operation==='delete'||!in_array(\$type,['spool','printer_slot'],true)"))throw new RuntimeException('Operator API permissions are too broad.');
if(!str_contains($backupService,'$allowedColumns[$table][$column]'))throw new RuntimeException('Backup restore column whitelist is missing.');
$layout=(string)file_get_contents(FM_ROOT.'/resources/views/layout.php');
if(!is_file(FM_ROOT.'/public/assets/app-icon.png')||!str_contains($layout,'/assets/app-icon.png'))throw new RuntimeException('Application icon is missing from the shared layout.');
foreach(['https://github.com/pihrt-com/filamentmanager-mobile-app','https://play.google.com/store/apps/details?id=com.pihrt.filamentmanager.mobile'] as $mobileLink)if(!str_contains($layout,$mobileLink))throw new RuntimeException('Missing mobile application link '.$mobileLink);
foreach(["'/printers', [PrinterController::class, 'save'], [\$manager]","'/materials', [MaterialController::class, 'save'], [\$manager]","'/locations', [LocationController::class, 'save'], [\$manager]","'/spools', [SpoolController::class, 'save'], [\$inventoryEditor]"] as $guard)if(!str_contains($webRoutes,$guard))throw new RuntimeException('Missing route-level write authorization: '.$guard);
echo "Smoke tests passed.\n";
