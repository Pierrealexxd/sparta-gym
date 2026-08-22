<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SetUtf8Charset::class);
        $middleware->alias([
            'rol'         => \App\Http\Middleware\EnsureRole::class,
            'permiso'     => \App\Http\Middleware\EnsurePermission::class,
            'sede.activa' => \App\Http\Middleware\EstablecerSedeActiva::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
