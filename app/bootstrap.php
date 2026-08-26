<?php

declare(strict_types=1);

use FilamentManager\Core\App;

define('FM_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'FilamentManager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = FM_ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$appConfig = require FM_ROOT . '/config/app.php';
$localFile = FM_ROOT . '/config/local.php';
$localConfig = is_file($localFile) ? require $localFile : [];

return new App(array_replace_recursive($appConfig, $localConfig));
