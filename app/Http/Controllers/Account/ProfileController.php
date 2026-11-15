<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private PointService $points) {}

    public function show(Request $request): View
    {
        $user = $request->user()->load(['group', 'socialAccounts']);

        $stats = [
            'orders' => $user->orders()->count(),
            'success' => $user->orders()->where('status', Order::SUCCESS)->count(),
            'spent' => (float) $user->orders()->where('status', Order::SUCCESS)->sum('price'),
            'referrals' => $user->referrals()->count(),
        ];

        return view('account.profile', [
            'user' => $user,
            'stats' => $stats,
            'checklist' => $user->profileChecklist(),
            'bonus' => PointService::rules()['profile_bonus'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [$user->email ? 'nullable' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s\-]{6,20}$/'],
            'shop_name' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:60'],
            'preferred_locale' => ['required', Rule::in(array_keys(config('unlockhub.locales')))],
            'preferred_currency' => ['required', Rule::in(config('unlockhub.currencies'))],
            'country' => ['nullable', Rule::in(array_keys(config('unlockhub.countries')))],
        ]);

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'shop_name' => $data['shop_name'] ?? null,
            'city' => $data['city'] ?? null,
            'preferred_locale' => $data['preferred_locale'],
            'preferred_currency' => $data['preferred_currency'],
            'country' => $data['country'] ?? null,
            'notify_email' => $request->boolean('notify_email'),
        ]);

        // Email can be added once here (accounts from Telegram/TikTok); changing it later goes through support
        if (! $user->email && filled($data['email'] ?? null)) {
            $user->email = strtolower($data['email']);
            $user->email_verified_at = null;
        }

        $user->save();
        session(['locale' => $user->preferred_locale]);
        session()->forget('pay_country');

        $msg = 'Profile saved.';
        if ($awarded = $this->points->maybeAwardProfileBonus($user)) {
            $msg .= " Profile complete — +{$awarded} points!";
        }

        return back()->with('success', $msg);
    }

    public function avatar(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64']]);

        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => $request->file('avatar')->store('avatars', 'public')])->save();

        $msg = 'Photo updated.';
        if ($awarded = $this->points->maybeAwardProfileBonus($user)) {
            $msg .= " Profile complete — +{$awarded} points!";
        }

        return back()->with('success', $msg);
    }

    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return back()->with('success', 'Photo removed.');
    }
}
