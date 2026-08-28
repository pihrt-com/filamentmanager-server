<?php

declare(strict_types=1);

use FilamentManager\Controllers\AuthController;
use FilamentManager\Controllers\DashboardController;
use FilamentManager\Controllers\MaterialController;
use FilamentManager\Controllers\PrinterController;
use FilamentManager\Controllers\SettingsController;
use FilamentManager\Controllers\SpoolController;
use FilamentManager\Controllers\UserController;
use FilamentManager\Controllers\LocationController;
use FilamentManager\Controllers\AuditController;
use FilamentManager\Core\Router;
use FilamentManager\Core\Response;

$router = new Router();
$webUser = static function ($request, $app): void {
    if (!$app->auth()->user()) Response::redirect($request->basePath() . '/login');
};
$admin = static function ($request, $app): void {
    if (!$app->auth()->user()) Response::redirect($request->basePath() . '/login');
    $app->auth()->requireRole('admin');
};
$manager = static function ($request, $app): void {
    if (!$app->auth()->user()) Response::redirect($request->basePath() . '/login');
    $app->auth()->requireRole('admin', 'manager');
};
$inventoryEditor = static function ($request, $app): void {
    if (!$app->auth()->user()) Response::redirect($request->basePath() . '/login');
    $app->auth()->requireRole('admin', 'manager', 'operator');
};

$router->get('/login', [AuthController::class, 'form']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], [$webUser]);
$router->get('/', [DashboardController::class, 'index'], [$webUser]);
$router->get('/printers/new', [PrinterController::class, 'form'], [$manager]);
$router->post('/printers', [PrinterController::class, 'save'], [$manager]);
$router->get('/printers/{id}/edit', [PrinterController::class, 'form'], [$manager]);
$router->post('/printers/{id}', [PrinterController::class, 'save'], [$manager]);
$router->post('/printers/{id}/delete', [PrinterController::class, 'delete'], [$manager]);
$router->get('/spools', [SpoolController::class, 'index'], [$webUser]);
$router->get('/spools/new', [SpoolController::class, 'form'], [$inventoryEditor]);
$router->post('/spools', [SpoolController::class, 'save'], [$inventoryEditor]);
$router->get('/spools/{id}/edit', [SpoolController::class, 'form'], [$inventoryEditor]);
$router->post('/spools/{id}', [SpoolController::class, 'save'], [$inventoryEditor]);
$router->post('/spools/{id}/delete', [SpoolController::class, 'delete'], [$manager]);
$router->get('/materials', [MaterialController::class, 'index'], [$webUser]);
$router->post('/materials', [MaterialController::class, 'save'], [$manager]);
$router->get('/materials/{id}/edit', [MaterialController::class, 'index'], [$manager]);
$router->post('/materials/{id}', [MaterialController::class, 'save'], [$manager]);
$router->post('/materials/{id}/delete', [MaterialController::class, 'delete'], [$manager]);
$router->get('/locations', [LocationController::class, 'index'], [$webUser]);
$router->post('/locations', [LocationController::class, 'save'], [$manager]);
$router->get('/locations/{id}/edit', [LocationController::class, 'index'], [$manager]);
$router->post('/locations/{id}', [LocationController::class, 'save'], [$manager]);
$router->post('/locations/{id}/delete', [LocationController::class, 'delete'], [$manager]);
$router->get('/admin/users', [UserController::class, 'index'], [$admin]);
$router->post('/admin/users', [UserController::class, 'save'], [$admin]);
$router->get('/admin/users/{id}/edit', [UserController::class, 'index'], [$admin]);
$router->post('/admin/users/{id}', [UserController::class, 'save'], [$admin]);
$router->post('/admin/users/{id}/toggle', [UserController::class, 'toggle'], [$admin]);
$router->post('/admin/users/{id}/delete', [UserController::class, 'delete'], [$admin]);
$router->get('/admin/settings', [SettingsController::class, 'index'], [$admin]);
$router->get('/admin/audit', [AuditController::class, 'index'], [$admin]);
$router->post('/admin/settings/backup', [SettingsController::class, 'backup'], [$admin]);
$router->post('/admin/settings/backup/delete', [SettingsController::class, 'deleteBackup'], [$admin]);
$router->post('/admin/settings/device/revoke', [SettingsController::class, 'revokeDevice'], [$admin]);
$router->post('/admin/settings/restore', [SettingsController::class, 'restore'], [$admin]);
$router->post('/admin/settings/update/check', [SettingsController::class, 'checkUpdate'], [$admin]);
$router->post('/admin/settings/update/install', [SettingsController::class, 'installUpdate'], [$admin]);

return $router;
