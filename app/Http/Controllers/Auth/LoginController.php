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
use Illuminate\Support\Facades\RateLimiter;
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

        $key = Str::lower($data['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $data['login'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['login' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['login' => 'This account is suspended. Please contact support.']);
        }

        $remember = $request->boolean('remember');

        // Trusted browser: skip the second step
        if ($this->devices->isTrusted($user, $request)) {
            return $this->finishLogin($request, $user, $remember);
        }

        $this->startPending($request, $user, $remember);

        if (! $user->hasTwoFactor() && $this->otp->cooldown($user) === 0) {
            $this->otp->send($user);
        }

        return redirect()->route('login.challenge');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
