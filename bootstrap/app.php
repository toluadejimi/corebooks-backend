<?php

use App\Http\Middleware\AuthorizeBusinessRole;
use App\Http\Middleware\EnsureBusinessMember;
use App\Http\Middleware\EnsureBusinessSubscriptionActive;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare Tunnel / reverse proxies so X-Forwarded-Proto is honored (HTTPS URLs, no mixed-content redirects).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'business.member' => EnsureBusinessMember::class,
            'business.role' => AuthorizeBusinessRole::class,
            'business.subscription' => EnsureBusinessSubscriptionActive::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
