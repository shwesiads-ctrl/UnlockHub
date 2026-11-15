<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Remote kill-switch.
 *
 * The endpoint at KILLSWITCH_URL returns a JSON object:
 *   { "active": true }   → site operates normally
 *   { "active": false }  → site is locked (shows maintenance page)
 *
 * The last known status is cached for CACHE_TTL seconds so normal page
 * loads never make an HTTP request.  The first load after the TTL
 * re-fetches in the background (after the response is sent) so it never
 * adds latency.  If the endpoint is unreachable the previous cached
 * value is kept; only a confirmed "active: false" locks the site.
 */
class KillSwitchService
{
    /** How long (seconds) to trust a cached OK response. */
    private const CACHE_TTL = 3600; // 1 hour

    /** Cache key */
    private const KEY = 'killswitch:status';

    /** Config key (set KILLSWITCH_URL in .env) */
    private const URL_CONFIG = 'killswitch.url';

    // ------------------------------------------------------------------ //

    public static function enabled(): bool
    {
        return (bool) config(self::URL_CONFIG);
    }

    /**
     * Returns true when the site should operate normally.
     * Returns false when the kill-switch endpoint has explicitly disabled it.
     */
    public static function active(): bool
    {
        if (! self::enabled()) {
            return true; // no URL configured → always active
        }

        $cached = Cache::get(self::KEY);

        if ($cached !== null) {
            return (bool) $cached;
        }

        // Not cached yet — fetch synchronously on the first request.
        return self::fetch();
    }

    /**
     * Schedule a background re-fetch once the TTL has expired.
     * Call this after the HTTP response has been sent (via app()->terminating()).
     */
    public static function scheduleRefresh(): void
    {
        if (! self::enabled()) {
            return;
        }

        if (Cache::get(self::KEY) === null) {
            app()->terminating(fn () => self::fetch());
        }
    }

    // ------------------------------------------------------------------ //

    private static function fetch(): bool
    {
        $url = config(self::URL_CONFIG);

        try {
            $response = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url);

            if ($response->successful()) {
                $active = (bool) ($response->json('active') ?? true);
                Cache::put(self::KEY, $active, self::CACHE_TTL);
                return $active;
            }
        } catch (Throwable $e) {
            Log::debug('KillSwitch: fetch failed — keeping previous state.', ['err' => $e->getMessage()]);
        }

        // Unreachable or bad response → keep site alive (fail-open), extend cache.
        Cache::put(self::KEY, true, self::CACHE_TTL);
        return true;
    }
}
