<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\CompletesLogin;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\PointService;
use App\Services\Social\SocialProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Continue with Google / Facebook / Discord / TikTok / Telegram".
 * - Known social account → sign in (Google Authenticator still asked if the user turned it on).
 * - Signed-in user → link the account (Settings › Connected accounts).
 * - New person → create an account (email filled when the provider gives a verified one).
 */
class SocialAuthController extends Controller
{
    use CompletesLogin;

    public function __construct(private SocialProviders $providers, private PointService $points) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless($provider !== 'telegram' && SocialProviders::isEnabled($provider), 404);

        $state = Str::random(40);
        $request->session()->put('social.state', $state);
        $request->session()->put('social.intent', $request->user() ? 'link' : 'login');

        return redirect()->away($this->providers->authorizeUrl($provider, $state));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless($provider !== 'telegram' && SocialProviders::isEnabled($provider), 404);

        $state = $request->session()->pull('social.state');
        if (! $state || ! hash_equals($state, (string) $request->query('state'))) {
            return $this->fail($request, 'Login session expired. Please try again.');
        }

        if ($request->query('error') || ! $request->query('code')) {
            return $this->fail($request, 'Login was cancelled.');
        }

        try {
            $profile = $this->providers->user($provider, (string) $request->query('code'));
        } catch (Throwable $e) {
            report($e);

            return $this->fail($request, SocialProviders::PROVIDERS[$provider]['label'].' login failed. Please try again.');
        }

        return $this->complete($request, $provider, $profile);
    }

    /** Telegram Login Widget redirects here with signed data in the query string. */
    public function telegram(Request $request): RedirectResponse
    {
        abort_unless(SocialProviders::isEnabled('telegram'), 404);

        try {
            $profile = $this->providers->verifyTelegram($request->query());
        } catch (Throwable $e) {
            return $this->fail($request, $e->getMessage());
        }

        $request->session()->put('social.intent', $request->user() ? 'link' : 'login');

        return $this->complete($request, 'telegram', $profile);
    }

    public function unlink(Request $request, string $provider): RedirectResponse
    {
        $user = $request->user();
        $account = $user->socialAccounts()->where('provider', $provider)->firstOrFail();

        // Don't lock the user out: they need a password or another connected login
        if (! $user->password_set && $user->socialAccounts()->count() <= 1) {
            return back()->with('error', 'Set a password first, or connect another login — otherwise you could not sign in again.');
        }

        $account->delete();

        return back()->with('success', SocialProviders::PROVIDERS[$provider]['label'].' disconnected.');
    }

    private function complete(Request $request, string $provider, array $profile): RedirectResponse
    {
        $label = SocialProviders::PROVIDERS[$provider]['label'];
        $intent = $request->session()->pull('social.intent', 'login');
        $account = SocialAccount::where('provider', $provider)->where('provider_id', $profile['id'])->first();

        // Linking from Settings
        if ($intent === 'link' && $request->user()) {
            if ($account && $account->user_id !== $request->user()->id) {
                return redirect()->route('account.security')->with('error', "This {$label} account is already connected to another user.");
            }
            SocialAccount::updateOrCreate(
                ['user_id' => $request->user()->id, 'provider' => $provider],
                ['provider_id' => $profile['id'], 'email' => $profile['email'], 'name' => $profile['name'], 'avatar_url' => $profile['avatar']]
            );

            return redirect()->route('account.security')->with('success', "{$label} connected. You can now log in with it.");
        }

        // Existing social login
        if ($account) {
            $user = $account->user;
            $account->update(['last_login_at' => now(), 'avatar_url' => $profile['avatar'] ?? $account->avatar_url]);

            return $this->signIn($request, $user);
        }

        // Email already registered: don't merge automatically (could be someone else's email)
        if ($profile['email'] && User::where('email', strtolower($profile['email']))->exists()) {
            return redirect()->route('login')->with('error',
                "An account with {$profile['email']} already exists. Log in with your password, then connect {$label} in Settings.");
        }

        // New account
        $user = DB::transaction(function () use ($request, $provider, $profile) {
            $user = new User([
                'name' => Str::limit($profile['name'] ?: 'User', 100, ''),
                'username' => SocialProviders::suggestUsername($profile['username'] ?? null, $profile['name'], $profile['email']),
                'email' => $profile['email'] ? strtolower($profile['email']) : null,
                'password' => Hash::make(Str::random(40)),
                'referred_by' => ($ref = $request->cookie('uh_ref')) ? User::where('referral_code', $ref)->value('id') : null,
            ]);
            $user->forceFill([
                'password_set' => false,
                'email_verified_at' => $profile['email'] && $profile['email_verified'] ? now() : null,
            ])->save();

            SocialAccount::create([
                'user_id' => $user->id, 'provider' => $provider, 'provider_id' => $profile['id'],
                'email' => $profile['email'], 'name' => $profile['name'], 'avatar_url' => $profile['avatar'],
                'last_login_at' => now(),
            ]);

            return $user;
        });

        $this->points->awardSignup($user);
        ActivityLog::record('user.registered_social', $user, ['provider' => $provider]);

        $redirect = $this->signIn($request, $user);

        return $user->email
            ? $redirect->with('success', "Welcome! Your account was created with {$label}.")
            : redirect()->route('account.profile')->with('success', "Welcome! Add your email so we can send order results and receipts.");
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        if (! $user->isActive()) {
            return redirect()->route('login')->with('error', 'This account is suspended. Please contact support.');
        }

        // Respect Google Authenticator: social login replaces the password, not the 2FA step
        if ($user->hasTwoFactor()) {
            $this->startPending($request, $user, true);

            return redirect()->route('login.challenge');
        }

        return $this->finishLogin($request, $user, true);
    }

    private function fail(Request $request, string $message): RedirectResponse
    {
        return redirect()->route($request->user() ? 'account.security' : 'login')->with('error', $message);
    }
}
