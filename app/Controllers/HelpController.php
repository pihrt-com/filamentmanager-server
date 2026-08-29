<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;
use FilamentManager\Core\Request;
use FilamentManager\Core\View;

final class HelpController
{
    public function __construct(private readonly App $app) {}

    public function index(Request $request): void
    {
        $this->app->auth()->requireUser();
        View::render('help', ['title' => View::t('help'), 'basePath' => $request->basePath()]);
    }
}
