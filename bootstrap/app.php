<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        // Real visitor IP behind Cloudflare (runs before everything, incl. rate limits)
        $middleware->prepend(\App\Http\Middleware\CloudflareProxy::class);
        // Clickjacking / MIME-sniffing / referrer / HSTS headers on every response
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // White-label reseller shops (after the session so we know who is logged in)
        $middleware->web(append: [\App\Http\Middleware\ResolveResellerShop::class]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'staff' => \App\Http\Middleware\CheckStaffPermission::class,
            'nostore' => \App\Http\Middleware\NoStore::class,
            'reseller' => \App\Http\Middleware\EnsureReseller::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('account.dashboard'));

        // Remember ?ref=CODE so the register form can prefill it
        $middleware->web(append: [
            \App\Http\Middleware\CaptureReferral::class,
            \App\Http\Middleware\SetLocale::class,
            // Domain lock / serial key (after the language is known, so the locked page is translated)
            \App\Http\Middleware\EnforceLicense::class,
        ]);
        $middleware->api(append: [\App\Http\Middleware\EnforceLicense::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
