<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Profile, password, Google Authenticator and trusted devices. */
class SecurityController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private TrustedDeviceService $devices,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $qr = null;

        // Setup in progress: secret saved but not confirmed yet
        if ($user->two_factor_secret && ! $user->two_factor_confirmed_at) {
            $qr = $this->twoFactor->qrSvg($user, $user->two_factor_secret);
        }

        $user->load('socialAccounts');

        return view('account.security', [
            'user' => $user,
            'qr' => $qr,
            'devices' => $user->trustedDevices()->where('expires_at', '>', now())->latest('last_used_at')->paginate(10, ['*'], 'devices_page')->withQueryString(),
            'currentDeviceId' => $this->devices->find($user, $request)?->id,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s\-]{6,20}$/'],
            'preferred_locale' => ['required', Rule::in(array_keys(config('unlockhub.locales')))],
            'preferred_currency' => ['required', Rule::in(config('unlockhub.currencies'))],
        ]);

        $data['notify_email'] = $request->boolean('notify_email');
        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => $user->password_set ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->forceFill(['password' => $request->input('password'), 'password_set' => true])->save();

        return back()->with('success', 'Password saved.');
    }

    public function startTwoFactor(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactor()) {
            return back();
        }

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', 'Scan the QR code with Google Authenticator, then enter the 6-digit code.');
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        if (! $user->two_factor_secret || ! $this->twoFactor->verify($user->two_factor_secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'That code is not correct. Check the time on your phone and try again.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return back()->with('success', 'Google Authenticator is now on.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validateWithBag('twoFactor', ['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', 'Google Authenticator turned off. Email codes will be used instead.');
    }

    public function forgetDevice(Request $request, TrustedDevice $device): RedirectResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);
        $device->delete();

        return back()->with('success', 'Device removed.');
    }

    public function forgetAllDevices(Request $request): RedirectResponse
    {
        $this->devices->forgetAll($request->user());

        return back()->with('success', 'All trusted devices removed.');
    }
}
