<?php

declare(strict_types=1);

return [
    'name' => 'FilamentManager Server',
    'version_file' => dirname(__DIR__) . '/VERSION',
    'default_locale' => 'cs',
    'supported_locales' => ['cs', 'en'],
    'timezone' => 'Europe/Prague',
    'session_name' => 'filamentmanager_session',
    'github_repository' => 'pihrt-com/filamentmanager-server',
    'mobile_repository_url' => 'https://github.com/pihrt-com/filamentmanager-mobile-app',
    'update_check_interval' => 21600,
    'access_token_ttl' => 900,
    'refresh_token_ttl' => 2592000,
    'backup_retention' => 10,
];
