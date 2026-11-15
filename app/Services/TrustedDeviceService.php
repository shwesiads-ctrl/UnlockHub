<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * "Trust this device?" — a long-lived (encrypted) cookie that lets a user
 * skip the second login step on this browser.
 */
class TrustedDeviceService
{
    public const COOKIE = 'uh_trusted_device';

    public function isTrusted(User $user, Request $request): bool
    {
        $device = $this->find($user, $request);

        if (! $device) {
            return false;
        }

        $device->update(['last_used_at' => now(), 'ip_address' => $request->ip()]);

        return true;
    }

    public function trust(User $user, Request $request): void
    {
        $days = (int) config('unlockhub.otp.trusted_device_days', 30);
        $token = Str::random(64);

        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        Cookie::queue(self::COOKIE, $user->id.'|'.$token, $days * 24 * 60, null, null, null, true, false, 'lax');
    }

    /** The device record matching this browser's cookie, if any. */
    public function find(User $user, Request $request): ?TrustedDevice
    {
        $raw = (string) $request->cookie(self::COOKIE);

        if (! str_contains($raw, '|')) {
            return null;
        }

        [$userId, $token] = explode('|', $raw, 2);

        if ((int) $userId !== $user->id) {
            return null;
        }

        return TrustedDevice::where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }

    public function forgetAll(User $user): void
    {
        TrustedDevice::where('user_id', $user->id)->delete();
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
