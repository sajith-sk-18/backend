<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Token-only Sanctum: no cookie session, no CSRF on /api/*.
        // Bearer token from the admin panel goes through auth:sanctum directly.
        // (Do NOT call $middleware->statefulApi() — it adds the
        // EnsureFrontendRequestsAreStateful middleware that triggers CSRF
        // on requests whose Origin matches SANCTUM_STATEFUL_DOMAINS.)
        $middleware->alias([
            'admin.only' => \App\Http\Middleware\AdminOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
