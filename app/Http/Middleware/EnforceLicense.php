<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the site when its serial key is missing, suspended, revoked, expired,
 * used on another domain, or the license server has been unreachable too long.
 * Owners can still log in and fix it in Admin › License.
 */
class EnforceLicense
{
    /** Paths that always work, so the owner can log in and activate. */
    private const OPEN = ['admin/license', 'admin/license/*', 'login', 'login/*', 'logout', 'locale/*', 'up', 'install.php', 'account/security', 'account/security/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! LicenseService::enabled() || LicenseService::isDevInstall($request)) {
            return $next($request);
        }

        $status = LicenseService::status($request);

        // Locked, but maybe it was fixed on the server (renewed, domain moved): ask again, at most every 10 minutes.
        if (! $status['valid'] && $status['refresh_due'] && LicenseService::serial() && Cache::add('license:retry', 1, 600)) {
            LicenseService::refresh();
            $status = LicenseService::status($request);
        }

        // Working: refresh in the background once a day (after the page is sent).
        if ($status['valid'] && $status['refresh_due'] && Cache::add('license:refresh', 1, 3600)) {
            app()->terminating(fn () => LicenseService::refresh());
        }

        if ($status['valid'] || $request->is(...self::OPEN)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['ERROR' => [['MESSAGE' => 'Site license inactive']], 'message' => 'Site license inactive.'], 503);
        }

        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.license');
        }

        return response()->view('license.locked', ['status' => $status], 503);
    }
}
