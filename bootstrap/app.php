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
    ->withMiddleware(function (Middleware $middleware): void {
        // In production the app only ever receives requests from the VPS's
        // own host-level Nginx (SSL termination happens there — see
        // DEPLOIEMENT.md step 8), never directly from the public internet.
        // Without this, Laravel has no way to know the original request
        // was HTTPS and generates http:// asset/route URLs, which the
        // browser then blocks as mixed content on an https:// page (silent
        // CSS/JS failure — the page loads, nothing looks styled).
        $middleware->trustProxies(at: '*');

        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
