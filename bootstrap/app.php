<?php

use App\Http\Middleware\SessionIdleTimeout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // HostForge terminates TLS at its proxy/LB and forwards http
        // internally. Without this Laravel sees every request as http, so
        // `Secure` session/CSRF cookies are never set and every stateful
        // POST 419s in production.
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->appendToGroup('web', SessionIdleTimeout::class);
        $middleware->appendToGroup('api', SessionIdleTimeout::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
