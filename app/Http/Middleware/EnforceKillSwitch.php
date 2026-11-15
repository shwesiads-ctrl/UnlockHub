<?php

namespace App\Http\Middleware;

use App\Services\KillSwitchService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the remote kill-switch endpoint.
 * If it returns { "active": false } the site is put into maintenance mode.
 * Admins are shown a simple admin-login link so they can still access the backend.
 * The endpoint is checked at most every hour (cached); a failed request keeps the site alive.
 */
class EnforceKillSwitch
{
    /** These paths are always reachable so the owner can still log in. */
    private const OPEN = [
        'admin/*',
        'login',
        'login/*',
        'logout',
        'locale/*',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! KillSwitchService::enabled()) {
            return $next($request);
        }

        // Admin routes and health-check are always open.
        if ($request->is(...self::OPEN)) {
            return $next($request);
        }

        $active = KillSwitchService::active();

        if ($active) {
            // Schedule a silent background refresh for next TTL.
            app()->terminating(fn () => KillSwitchService::scheduleRefresh());
            return $next($request);
        }

        // Site is deactivated by the kill-switch.
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error'   => 'Service temporarily unavailable.',
                'message' => 'Service temporarily unavailable.',
            ], 503);
        }

        return response()->view('errors.killswitch', [], 503);
    }
}
