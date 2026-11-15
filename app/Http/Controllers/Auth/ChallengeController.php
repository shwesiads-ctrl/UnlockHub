<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\CompletesLogin;
use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Second login step: Google Authenticator code or email OTP. */
class ChallengeController extends Controller
{
    use CompletesLogin;

    public function __construct(
        private OtpService $otp,
        private TwoFactorService $twoFactor,
        private TrustedDeviceService $devices,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Your sign-in session expired. Please log in again.');
        }

        return view('auth.challenge', [
            'method' => $user->hasTwoFactor() ? 'totp' : 'email',
            'maskedEmail' => $this->mask($user->email),
            'cooldown' => $this->otp->cooldown($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Your sign-in session expired. Please log in again.');
        }

        $request->validate(['code' => ['required', 'string', 'max:10']]);

        $key = 'challenge|'.$user->id;
        if ($wait = \App\Support\LoginThrottle::waitFor($key, $request->ip())) {
            $this->tooMany($wait);
        }

        $ok = $user->hasTwoFactor()
            ? $this->twoFactor->verify($user->two_factor_secret, $request->input('code'))
            : $this->otp->verify($user, $request->input('code'));

        if (! $ok) {
            $hit = \App\Support\LoginThrottle::hit($key, $request->ip(), 6);
            if ($hit['locked']) {
                \App\Models\ActivityLog::record('login.locked', $user, ['step' => 'code', 'ip' => $request->ip(), 'seconds' => $hit['locked']]);
                $this->tooMany($hit['locked']);
            }
            throw ValidationException::withMessages(['code' => __('That code is not correct or has expired.')]);
        }

        \App\Support\LoginThrottle::clear($key);

        // A correct email code also proves the address
        if (! $user->hasTwoFactor() && ! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if ($request->boolean('trust_device')) {
            $this->devices->trust($user, $request);
        }

        return $this->finishLogin($request, $user);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasTwoFactor()) {
            return back();
        }

        if ($wait = $this->otp->cooldown($user)) {
            return back()->with('error', "Please wait {$wait} seconds before requesting a new code.");
        }

        $this->otp->send($user);

        return back()->with('success', 'A new code has been sent.');
    }

    private function mask(?string $email): string
    {
        if (! $email) {
            return '—';
        }

        [$name, $domain] = explode('@', $email, 2) + [1 => ''];

        return mb_substr($name, 0, 2).str_repeat('•', max(1, mb_strlen($name) - 2)).'@'.$domain;
    }

    private function tooMany(int $seconds): never
    {
        session()->flash('login_wait_until', now()->getTimestamp() + $seconds);

        throw ValidationException::withMessages([
            'code' => __('Too many wrong tries. Please wait :time and try again.', ['time' => \App\Support\LoginThrottle::human($seconds)]),
        ]);
    }
}
