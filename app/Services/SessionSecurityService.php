<?php

namespace App\Services;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionSecurityService
{
    public const KEY = 'auth.session_version';

    public static function stamp(Request $request, User $user): void
    {
        $request->session()->put(self::KEY, ['user_id' => $user->id, 'version' => (int) $user->session_version]);
    }

    public function changePassword(User $user, string $password): User
    {
        return DB::transaction(function () use ($user, $password) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'password' => $password,
                'password_set' => true,
                'remember_token' => Str::random(60),
                'session_version' => (int) $locked->session_version + 1,
            ])->save();

            app(TrustedDeviceService::class)->forgetAll($locked);
            LoginOtp::where('user_id', $locked->id)->delete();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $locked->id)->delete();
            }

            return $locked;
        });
    }
}
