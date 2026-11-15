<?php

namespace App\Services;

use App\Models\LoginOtp;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Support\Facades\Hash;

/** Email one-time codes (login second step, sensitive actions). */
class OtpService
{
    public const RESEND_SECONDS = 60;

    /** Create a new code (invalidating older ones) and email it. */
    public function send(User $user, string $purpose = 'login'): LoginOtp
    {
        LoginOtp::where('user_id', $user->id)->where('purpose', $purpose)
            ->whereNull('used_at')->update(['used_at' => now()]);

        $length = (int) config('unlockhub.otp.length', 6);
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        $otp = LoginOtp::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('unlockhub.otp.ttl_minutes', 10)),
        ]);

        $user->notify(new LoginOtpNotification($code, $purpose));

        return $otp;
    }

    /** Seconds until another code may be sent (0 = can send now). */
    public function cooldown(User $user, string $purpose = 'login'): int
    {
        $last = LoginOtp::where('user_id', $user->id)->where('purpose', $purpose)->latest('id')->first();

        if (! $last) {
            return 0;
        }

        return max(0, self::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now()));
    }

    public function verify(User $user, string $code, string $purpose = 'login'): bool
    {
        $otp = LoginOtp::where('user_id', $user->id)->where('purpose', $purpose)
            ->whereNull('used_at')->latest('id')->first();

        if (! $otp || ! $otp->isUsable()) {
            return false;
        }

        if (! Hash::check(trim($code), $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['used_at' => now()]);

        return true;
    }
}
