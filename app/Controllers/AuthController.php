<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;
use FilamentManager\Core\Csrf;
use FilamentManager\Core\Request;
use FilamentManager\Core\Response;
use FilamentManager\Core\View;
use FilamentManager\Services\AuditService;

final class AuthController
{
    public function __construct(private readonly App $app) {}
    public function form(Request $request): void
    {
        if ($this->app->auth()->user()) Response::redirect($request->basePath() . '/');
        View::render('login', ['title' => 'FilamentManager Server', 'basePath' => $request->basePath()]);
    }
    public function login(Request $request): void
    {
        Csrf::verify($request);
        if ($this->app->auth()->attempt(trim((string) $request->input('username')), (string) $request->input('password'))) {
            (new AuditService($this->app))->record('auth.login');
            Response::redirect($request->basePath() . '/');
        }
        View::render('login', ['title' => 'FilamentManager Server', 'basePath' => $request->basePath(), 'error' => $this->app->translator()->get('login_failed')]);
    }
    public function logout(Request $request): void
    {
        Csrf::verify($request);
        (new AuditService($this->app))->record('auth.logout');
        $this->app->auth()->logout();
        Response::redirect($request->basePath() . '/login');
    }
}
