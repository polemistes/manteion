<?php

use App\Http\Middleware\EnsureUserIsAdministrator;
use App\Http\Middleware\EnsureUserIsNotSuspended;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RestrictImpersonatedRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The exact upstream proxy IP on shared hosting is rarely known in advance,
        // so trust all proxies for X-Forwarded-* headers (https detection, client IP).
        $middleware->trustProxies(at: '*');

        // Set by the browser when the alpha notice is dismissed, and read on
        // the server so the first paint already leaves it out.
        $middleware->encryptCookies(except: ['alpha_notice_dismissed']);

        $middleware->web(append: [
            SetLocale::class,
            EnsureUserIsNotSuspended::class,
            RestrictImpersonatedRequests::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'administrator' => EnsureUserIsAdministrator::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
