<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'start.session' => \Illuminate\Session\Middleware\StartSession::class,
            'role' => \App\Http\Middleware\CheckRole::class, // Adding 'role' as well since it might be used in routes
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
//     protected $routeMiddleware = [
//
//     'team.member' => \App\Http\Middleware\CheckTeamMembership::class,
//     'team.leader' => \App\Http\Middleware\CheckTeamLeadership::class,
// ];
