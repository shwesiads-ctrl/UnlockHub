<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login is two steps: password, then (unless the device is trusted)
 * Google Authenticator if enabled, otherwise an email code.
 * Between the steps the user id is kept in the session as "login.pending".
 */
trait CompletesLogin
{
    protected const PENDING_KEY = 'login.pending';
    protected const PENDING_TTL_MINUTES = 15;

    protected function startPending(Request $request, User $user, bool $remember): void
    {
        $request->session()->put(self::PENDING_KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'expires' => now()->addMinutes(self::PENDING_TTL_MINUTES)->timestamp,
        ]);
    }

    protected function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING_KEY);

        if (! $pending || $pending['expires'] < now()->timestamp) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        return User::find($pending['user_id']);
    }

    protected function finishLogin(Request $request, User $user, ?bool $remember = null): RedirectResponse
    {
        $remember ??= (bool) data_get($request->session()->get(self::PENDING_KEY), 'remember', false);
        $request->session()->forget(self::PENDING_KEY);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $home = $user->isAdmin() ? route('admin.dashboard') : route('account.dashboard');

        return redirect()->intended($home);
    }
}
