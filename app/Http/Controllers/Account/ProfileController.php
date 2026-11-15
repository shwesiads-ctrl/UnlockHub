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
            'preferred_locale' => ['required', Rule::in([...array_keys(config('unlockhub.locales')), 'auto'])],
            'preferred_currency' => ['required', Rule::in(config('unlockhub.currencies'))],
            'country' => ['nullable', Rule::in(array_keys(config('unlockhub.countries')))],
        ]);

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'shop_name' => $data['shop_name'] ?? null,
            'city' => $data['city'] ?? null,
            // "auto" = follow the visitor's location; otherwise the language is fixed
            'preferred_locale' => $data['preferred_locale'] === 'auto' ? app()->getLocale() : $data['preferred_locale'],
            'locale_auto' => $data['preferred_locale'] === 'auto',
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
        if ($user->locale_auto) {
            session()->forget('locale');
        } else {
            session(['locale' => $user->preferred_locale]);
        }
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
        $this->replacePhoto($request, 'avatar', 'avatar_path', 'avatars');

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
            $old = $user->avatar_path;
            $user->forceFill(['avatar_path' => null])->save();
            Storage::disk('public')->delete($old);
        }

        return back()->with('success', 'Photo removed.');
    }

    private function replacePhoto(Request $request, string $input, string $column, string $directory): void
    {
        $user = $request->user();
        $old = $user->$column;
        $path = $request->file($input)->store($directory, 'public');
        try {
            $user->forceFill([$column => $path])->save();
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
        if ($old) Storage::disk('public')->delete($old);
    }

    public function cover(Request $request): RedirectResponse
    {
        $request->validate(['cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=600,min_height=180,max_width=6000,max_height=4000']]);
        $this->replacePhoto($request, 'cover', 'cover_path', 'covers');
        return back()->with('success', 'Cover photo updated.');
    }

    public function removeCover(Request $request): RedirectResponse
    {
        $old = $request->user()->cover_path;
        $request->user()->forceFill(['cover_path' => null])->save();
        if ($old) Storage::disk('public')->delete($old);
        return back()->with('success', 'Cover photo removed.');
    }

    public function verify(Request $request, \App\Models\User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['is_verified' => ['required', 'boolean']]);
        $user->forceFill(['is_verified' => (bool) $data['is_verified']])->save();
        \App\Models\ActivityLog::record('user.verification_updated', $user, ['verified' => $user->is_verified]);
        return back()->with('success', 'Verification badge updated.');
    }
}
