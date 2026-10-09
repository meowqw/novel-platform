<?php

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
        // API-first: never redirect guests to a missing web login route (would 500).
        $middleware->redirectGuestsTo(fn (): ?string => null);

        // Edge is Compose nginx (see docker/nginx/default.conf). Trust only private
        // hop networks — not '*' and not 127.0.0.1 (keeps PHPUnit from honouring
        // client-supplied X-Forwarded-For). If an external LB is added in front of
        // nginx, update trusted proxies and nginx forwarding explicitly.
        $middleware->trustProxies(
            at: [
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Preserve ProseMirror text-node whitespace (nested keys are dotted paths).
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
            'text',
            '*.text',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
