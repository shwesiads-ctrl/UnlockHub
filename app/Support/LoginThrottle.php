<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Login limits with a waiting time (Admin › Settings › Security › Login limits).
 * - N wrong tries for one account from one IP → wait X minutes.
 * - "Longer each time": every new lock doubles the wait (max 24 hours), forgotten after a day without locks.
 * - One IP trying many accounts is limited too (stops password-guessing across accounts).
 */
class LoginThrottle
{
    public static function maxAttempts(): int
    {
        return max(1, min(50, (int) setting('security.login_max', 5)));
    }

    public static function waitMinutes(): int
    {
        return max(1, min(1440, (int) setting('security.login_wait', 15)));
    }

    public static function progressive(): bool
    {
        return setting('security.login_progressive', '1') === '1';
    }

    public static function ipMax(): int
    {
        return max(5, (int) setting('security.login_ip_max', 30));
    }

    /** Seconds the visitor still has to wait (0 = may try). */
    public static function waitFor(string $key, string $ip): int
    {
        $until = max((int) Cache::get('login-lock:'.$key, 0), (int) Cache::get('login-lock:ip:'.$ip, 0));

        return max(0, $until - now()->getTimestamp());
    }

    /**
     * Record a wrong try.
     *
     * @return array{locked: int, left: int} seconds locked (0 = not locked) and tries left
     */
    public static function hit(string $key, string $ip, ?int $max = null): array
    {
        $max ??= self::maxAttempts();

        // Whole-IP limit (all accounts), 15-minute window
        $ipTries = self::bump('login-tries:ip:'.$ip, 900);
        if ($ipTries >= self::ipMax()) {
            Cache::forget('login-tries:ip:'.$ip);
            $secs = self::lock('ip:'.$ip);

            return ['locked' => $secs, 'left' => 0];
        }

        $tries = self::bump('login-tries:'.$key, self::waitMinutes() * 60);
        if ($tries >= $max) {
            Cache::forget('login-tries:'.$key);

            return ['locked' => self::lock($key), 'left' => 0];
        }

        return ['locked' => 0, 'left' => $max - $tries];
    }

    public static function clear(string $key): void
    {
        Cache::forget('login-tries:'.$key);
        Cache::forget('login-lock:'.$key);
        Cache::forget('login-locks:'.$key);
    }

    /** "1 minute 30 seconds"-style text in the current language. */
    public static function human(int $seconds): string
    {
        $seconds = max(1, $seconds);
        if ($seconds >= 3600) {
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);

            return trans_choice(':count hour|:count hours', $h, ['count' => $h]).($m ? ' '.trans_choice(':count minute|:count minutes', $m, ['count' => $m]) : '');
        }
        if ($seconds >= 60) {
            $m = (int) ceil($seconds / 60);

            return trans_choice(':count minute|:count minutes', $m, ['count' => $m]);
        }

        return trans_choice(':count second|:count seconds', $seconds, ['count' => $seconds]);
    }

    private static function lock(string $key): int
    {
        $locks = self::bump('login-locks:'.$key, 86400);
        $secs = self::waitMinutes() * 60;
        if (self::progressive()) {
            $secs = min(86400, $secs * (2 ** min(10, $locks - 1)));
        }
        Cache::put('login-lock:'.$key, now()->getTimestamp() + $secs, $secs);

        return $secs;
    }

    private static function bump(string $key, int $ttl): int
    {
        Cache::add($key, 0, $ttl);

        return (int) Cache::increment($key);
    }
}
