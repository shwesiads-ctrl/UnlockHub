<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\SessionSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'qa-not-a-real-telegram-token';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('social_login.telegram.enabled', '1');
        Setting::put('social_login.telegram.bot_username', 'qa_test_bot');
        Setting::put('social_login.telegram.bot_token', encrypt(self::BOT_TOKEN));
    }

    private function telegramPayload(string $state = ''): array
    {
        $fields = ['auth_date' => (string) now()->timestamp, 'first_name' => 'QA Telegram', 'id' => '987654321'];
        ksort($fields);
        $check = collect($fields)->map(fn ($value, $key) => $key.'='.$value)->implode("\n");
        return $fields + ['hash' => hash_hmac('sha256', $check, hash('sha256', self::BOT_TOKEN, true)), 'state' => $state];
    }

    public function test_valid_signed_telegram_payload_cannot_link_without_browser_intent(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->get('/auth/telegram/callback?'.http_build_query($this->telegramPayload()))
            ->assertRedirect(route('account.profile').'#social-accounts')->assertSessionHas('error');
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_telegram_link_is_bound_to_user_and_state_and_consumed_once(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->post('/auth/telegram/start')->assertOk();
        $state = session('social.telegram.state');
        $this->get('/auth/telegram/callback?'.http_build_query($this->telegramPayload('wrong-state')))->assertSessionHas('error');
        $this->assertSame(0, SocialAccount::count());
        $this->post('/auth/telegram/start')->assertOk();
        $state = session('social.telegram.state');
        $this->get('/auth/telegram/callback?'.http_build_query($this->telegramPayload($state)))->assertSessionHas('success');
        $this->assertSame($user->id, SocialAccount::firstOrFail()->user_id);
        $this->get('/auth/telegram/callback?'.http_build_query($this->telegramPayload($state)))->assertSessionHas('error');
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_expired_and_cross_user_telegram_flows_are_rejected(): void
    {
        $user = $this->customer();
        $other = $this->customer();
        $this->actingAs($user)->post('/auth/telegram/start')->assertOk();
        $state = session('social.telegram.state');
        $this->actingAs($other)->get('/auth/telegram/callback?'.http_build_query($this->telegramPayload($state)))->assertSessionHas('error');
        $this->post('/auth/telegram/start')->assertOk();
        $state = session('social.telegram.state');
        $this->travel(11)->minutes();
        $this->get('/auth/telegram/callback?'.http_build_query($this->telegramPayload($state)))->assertSessionHas('error');
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_guest_telegram_login_still_works_after_password_revocation(): void
    {
        $user = $this->customer();
        $user = app(SessionSecurityService::class)->changePassword($user, 'ChangedPassword123');
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'telegram', 'provider_id' => '987654321']);
        $this->post('/auth/telegram/start')->assertOk();
        $state = session('social.telegram.state');
        $this->get('/auth/telegram/callback?'.http_build_query($this->telegramPayload($state)))->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get('/account')->assertOk();
    }

    public function test_telegram_start_rejects_csrf_less_post_in_real_web_mode(): void
    {
        $this->app->instance('env', 'production');
        $this->post('/auth/telegram/start')->assertStatus(419);
        $this->withSession(['_token' => 'qa-csrf-token'])->post('/auth/telegram/start', ['_token' => 'qa-csrf-token'])->assertOk();
    }

    public function test_password_reset_revokes_old_sessions_devices_and_pending_codes(): void
    {
        $user = $this->customer();
        $oldRemember = $user->remember_token;
        TrustedDevice::create(['user_id' => $user->id, 'token_hash' => hash('sha256', 'qa-device'), 'expires_at' => now()->addDay()]);
        \App\Models\LoginOtp::create(['user_id' => $user->id, 'purpose' => 'login', 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)]);
        DB::table('sessions')->insert(['id' => 'qa-old-session', 'user_id' => $user->id, 'payload' => 'qa', 'last_activity' => time()]);
        config(['session.driver' => 'database']);
        $this->post('/reset-password', [
            'token' => Password::broker()->createToken($user), 'email' => $user->email,
            'password' => 'NewPassword456', 'password_confirmation' => 'NewPassword456',
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewPassword456', $fresh->password));
        $this->assertTrue($fresh->password_set);
        $this->assertSame(1, $fresh->session_version);
        $this->assertNotSame($oldRemember, $fresh->remember_token);
        $this->assertSame(0, $fresh->trustedDevices()->count());
        $this->assertSame(0, \App\Models\LoginOtp::where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        config(['session.driver' => 'array']);
        $this->actingAs($fresh)->withSession([SessionSecurityService::KEY => ['user_id' => $user->id, 'version' => 0]])
            ->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_change_keeps_current_session_but_rejects_old_and_legacy_sessions(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->put('/account/security/password', [
            'current_password' => 'password', 'password' => 'UpdatedPassword123', 'password_confirmation' => 'UpdatedPassword123',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/account')->assertOk();
        $fresh = $user->fresh();
        $remember = $fresh->remember_token;
        $this->assertSame(1, $fresh->session_version);
        $this->actingAs($fresh)->withSession([SessionSecurityService::KEY => ['user_id' => $user->id, 'version' => 0]])
            ->get('/account')->assertRedirect(route('login'));
        $this->assertSame($remember, $fresh->fresh()->remember_token);
        $this->actingAs($fresh);
        session()->forget(SessionSecurityService::KEY);
        $this->get('/account')->assertRedirect(route('login'));
    }

    public function test_password_reset_invalidates_pending_second_factor_login(): void
    {
        $user = $this->customer();
        $this->withSession(['login.pending' => [
            'user_id' => $user->id, 'remember' => false, 'expires' => now()->addMinutes(15)->timestamp, 'session_version' => 0,
        ]]);
        app(SessionSecurityService::class)->changePassword($user, 'ResetPassword123');
        $this->get('/login/verify')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('login.pending'));
    }

    public function test_old_remember_cookie_is_rejected_but_current_cookie_can_authenticate(): void
    {
        $user = $this->customer();
        $guard = Auth::guard('web');
        $cookieName = $guard->getRecallerName();
        $oldCookie = $user->id.'|'.$user->remember_token.'|'.$guard->hashPasswordForCookie($user->password);
        $updated = app(SessionSecurityService::class)->changePassword($user, 'UpdatedPassword123');
        $this->withCookie($cookieName, $oldCookie)->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
        Auth::forgetGuards(); // A new browser request has a fresh remember-cookie guard.
        $newCookie = $updated->id.'|'.$updated->remember_token.'|'.$guard->hashPasswordForCookie($updated->password);
        $this->withCookie($cookieName, $newCookie)->get('/account')->assertOk();
        $this->assertAuthenticatedAs($updated);
        $this->assertSame(1, session(SessionSecurityService::KEY.'.version'));
    }
}
