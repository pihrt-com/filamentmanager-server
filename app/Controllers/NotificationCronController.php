<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;
use FilamentManager\Core\Request;
use FilamentManager\Core\Response;
use FilamentManager\Services\NotificationCronService;

final class NotificationCronController
{
    public function __construct(private readonly App $app) {}

    public function run(Request $request,string $token): void
    {
        header('Cache-Control: no-store');
        Response::json((new NotificationCronService($this->app))->run($token));
    }
}
