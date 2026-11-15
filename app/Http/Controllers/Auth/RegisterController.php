<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\CompletesLogin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    use CompletesLogin;

    public function __construct(private OtpService $otp) {}

    public function show(Request $request): View
    {
        return view('auth.register', [
            'ref' => $request->query('ref', $request->cookie('uh_ref')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (app()->bound('reseller.shop') && app('reseller.shop_by_domain') && ! app('reseller.shop')->allow_signup) {
            return back()->with('error', 'Sign-up is closed for this shop. Please contact the shop owner.');
        }

        \App\Services\Captcha::check($request, 'register');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s\-]{6,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'referral_code' => ['nullable', 'string', 'max:16', 'exists:users,referral_code'],
            'terms' => ['accepted'],
        ], [
            'referral_code.exists' => 'This referral code does not exist.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => strtolower($data['username']),
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'referred_by' => isset($data['referral_code'])
                ? User::where('referral_code', strtoupper($data['referral_code']))->value('id')
                : null,
        ]);

        if (app()->bound('reseller.shop') && app('reseller.shop_by_domain')) {
            $user->forceFill(['parent_id' => app('reseller.shop')->user_id])->save();
        }

        app(\App\Services\PointService::class)->awardSignup($user);

        // Verify the email with a code before the first sign-in completes
        $this->startPending($request, $user, false);
        $this->otp->send($user);

        return redirect()->route('login.challenge')
            ->with('success', 'Account created. Enter the code we emailed you to finish.');
    }
}
