<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\CompletesLogin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Support\LoginThrottle;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    use CompletesLogin;

    public function __construct(
        private OtpService $otp,
        private TrustedDeviceService $devices,
    ) {}

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Waiting time after too many wrong tries (checked before anything else, even the bot check)
        $key = Str::lower($data['login']).'|'.$request->ip();
        if ($wait = LoginThrottle::waitFor($key, $request->ip())) {
            $this->tooMany($wait);
        }

        \App\Services\Captcha::check($request, 'login');

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $data['login'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $hit = LoginThrottle::hit($key, $request->ip());
            if ($hit['locked']) {
                \App\Models\ActivityLog::record('login.locked', $user, ['login' => Str::limit($data['login'], 60), 'ip' => $request->ip(), 'seconds' => $hit['locked']]);
                $this->tooMany($hit['locked']);
            }
            throw ValidationException::withMessages(['login' => __('These credentials do not match our records.')
                .($hit['left'] <= 3 ? ' '.trans_choice(':count try left before a waiting time.|:count tries left before a waiting time.', $hit['left'], ['count' => $hit['left']]) : '')]);
        }

        LoginThrottle::clear($key);

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['login' => 'This account is suspended. Please contact support.']);
        }

        $remember = $request->boolean('remember');

        // Trusted browser: skip the second step
        if ($this->devices->isTrusted($user, $request)) {
            return $this->finishLogin($request, $user, $remember);
        }

        // Accounts made with Telegram/TikTok may have no email yet → no email code possible
        if (! $user->hasTwoFactor() && ! $user->email) {
            throw ValidationException::withMessages([
                'login' => 'This account has no email for the login code. Use the social login you signed up with, then add an email in your profile.',
            ]);
        }

        $this->startPending($request, $user, $remember);

        if (! $user->hasTwoFactor() && $this->otp->cooldown($user) === 0) {
            $this->otp->send($user);
        }

        return redirect()->route('login.challenge');
    }

    /** Stop with a "please wait" message; the login page shows a live countdown. */
    private function tooMany(int $seconds): never
    {
        session()->flash('login_wait_until', now()->getTimestamp() + $seconds);

        throw ValidationException::withMessages([
            'login' => __('Too many wrong tries. Please wait :time and try again.', ['time' => LoginThrottle::human($seconds)]),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
