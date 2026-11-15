<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function turnstile(bool $verified): void
    {
        Setting::put('captcha.provider', 'turnstile');
        Setting::put('captcha.site_key', '1x00000000000000000000AA');
        Setting::put('captcha.secret', encrypt('1x0000000000000000000000000000000AA'));
        Setting::put('captcha.form.login', '1');
        Setting::put('captcha.verified_at', $verified ? now()->toDateTimeString() : null);
    }

    public function test_untested_captcha_is_not_enforced(): void
    {
        $this->turnstile(false);
        $this->post('/login', ['login' => 'nobody', 'password' => 'x'])->assertSessionDoesntHaveErrors('captcha');
    }

    public function test_login_needs_a_valid_token_when_active(): void
    {
        $this->turnstile(true);

        $this->post('/login', ['login' => 'nobody', 'password' => 'x'])->assertSessionHasErrors('captcha');

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
        $this->post('/login', ['login' => 'nobody', 'password' => 'x', 'cf-turnstile-response' => 'bad'])->assertSessionHasErrors('captcha');

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->post('/login', ['login' => 'nobody', 'password' => 'x', 'cf-turnstile-response' => 'ok'])->assertSessionDoesntHaveErrors('captcha');
    }

    public function test_cloudflare_real_ip_only_from_cloudflare_edges(): void
    {
        Setting::put('security.cloudflare', '1');
        Route::get('/_ip', fn () => request()->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '172.64.1.1'])->get('/_ip', ['CF-Connecting-IP' => '203.0.113.9'])->assertSee('203.0.113.9');
        // Spoofed header from a non-Cloudflare address is ignored
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->get('/_ip', ['CF-Connecting-IP' => '203.0.113.9'])->assertSee('198.51.100.7');
    }
}
