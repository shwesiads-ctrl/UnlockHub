<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applies the public maintenance and read-only demo modes. */
class SiteMode
{
    private const AUTH_PATHS = [
        'login', 'login/*', 'logout', 'register', 'forgot-password', 'reset-password/*',
        'auth/*', 'account/security', 'account/security/*', 'up', 'manifest.webmanifest',
        'email/preferences/*', 'build/*', 'storage/*',
    ];

    private const MAINTENANCE_PATHS = [
        'login', 'login/*', 'logout', 'forgot-password', 'reset-password/*', 'auth/*',
        'admin', 'admin/*', 'up', 'manifest.webmanifest', 'build/*', 'storage/*',
        'email/preferences/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Keep administrators able to operate the site and turn either mode off.
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        $maintenance = $this->maintenanceIsActive();
        $demo = setting('site.demo') === '1';
        $authOrAssetRequest = $request->is(...self::AUTH_PATHS);
        $maintenanceExempt = $request->is(...self::MAINTENANCE_PATHS);
        $paymentWebhook = $request->is('api/payments/*');

        // Let admins sign in, keep assets and payment confirmations reachable.
        if ($maintenance && ! $maintenanceExempt && ! $paymentWebhook) {
            return $this->respond(
                $request,
                'maintenance',
                Response::HTTP_SERVICE_UNAVAILABLE,
                (string) setting('site.maintenance_message', '')
            );
        }

        if ($demo && ! $authOrAssetRequest && ! $paymentWebhook
            && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $this->respond($request, 'demo', Response::HTTP_FORBIDDEN, (string) setting('site.demo_message', ''));
        }

        return $next($request);
    }

    private function maintenanceIsActive(): bool
    {
        if (setting('site.maintenance') !== '1') {
            return false;
        }

        try {
            $timezone = (string) config('unlockhub.timezone', 'Asia/Yangon');
            $now = CarbonImmutable::now($timezone);
            $startsAt = setting('site.maintenance_starts_at');
            $endsAt = setting('site.maintenance_ends_at');

            if ($startsAt && $now->lt(CarbonImmutable::parse($startsAt, $timezone))) {
                return false;
            }

            if ($endsAt && $now->greaterThanOrEqualTo(CarbonImmutable::parse($endsAt, $timezone))) {
                return false;
            }
        } catch (\Throwable) {
            // Invalid saved schedules should fail closed while maintenance is enabled.
        }

        return true;
    }

    private function respond(Request $request, string $view, int $status, string $message = ''): Response
    {
        $message = trim($message) ?: ($view === 'maintenance'
            ? 'UnlockHub is temporarily unavailable while we make improvements. Please try again in a little while.'
            : 'You can browse the site, but actions are temporarily disabled while demo mode is on.');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], $status);
        }

        $headers = [];
        if ($view === 'maintenance') {
            $endsAt = setting('site.maintenance_ends_at');
            $retryAfter = 300;
            if ($endsAt) {
                try {
                    $timezone = (string) config('unlockhub.timezone', 'Asia/Yangon');
                    $retryAfter = max(0, CarbonImmutable::now($timezone)->diffInSeconds(CarbonImmutable::parse($endsAt, $timezone), false));
                } catch (\Throwable) {
                    // Keep the default retry interval for invalid schedules.
                }
            }
            $headers['Retry-After'] = (string) $retryAfter;
        }

        return response()->view('site.'.$view, ['message' => $message], $status, $headers);
    }
}
