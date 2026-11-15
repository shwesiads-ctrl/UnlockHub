<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([['kbzpay', 'MM', 'MMK', 1], ['promptpay', 'TH', 'THB', 60], ['momo', 'VN', 'VND', 0.09]] as [$code, $c, $cur, $rate]) {
            PaymentMethod::create(['name' => strtoupper($code), 'code' => $code, 'country' => $c, 'currency' => $cur, 'rate' => $rate, 'account_number' => '1', 'min_amount' => 1, 'is_active' => true]);
        }
    }

    private function fromCloudflare(string $country): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.10'])->withHeaders(['CF-IPCountry' => $country]);
    }

    public function test_cloudflare_country_sets_language_and_payment(): void
    {
        $this->fromCloudflare('TH')->get('/')
            ->assertOk()->assertSee('lang="th"', false)->assertSee('PROMPTPAY')->assertDontSee('KBZPAY');
    }

    public function test_fake_cloudflare_header_from_other_ip_is_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->withHeaders(['CF-IPCountry' => 'TH', 'Accept-Language' => 'en-US'])
            ->get('/')->assertSee('lang="en"', false)->assertSee('KBZPAY');
    }

    public function test_browser_language_is_the_fallback(): void
    {
        $this->withHeaders(['Accept-Language' => 'vi-VN,vi;q=0.9,en;q=0.5'])->get('/')
            ->assertSee('lang="vi"', false)->assertSee('MOMO');
    }

    public function test_server_geoip_variable(): void
    {
        $this->withServerVariables(['GEOIP_COUNTRY_CODE' => 'MM'])->withHeaders(['Accept-Language' => 'en-US'])->get('/')
            ->assertSee('lang="my"', false)->assertSee('KBZPAY');
    }

    public function test_picked_language_and_saved_country_win(): void
    {
        $this->fromCloudflare('TH')->withSession(['locale' => 'en'])->get('/')->assertSee('lang="en"', false)->assertSee('PROMPTPAY');

        $user = User::factory()->create();
        $user->forceFill(['preferred_locale' => 'my', 'locale_auto' => false, 'country' => 'VN'])->save();
        $this->actingAs($user)->fromCloudflare('TH')->get('/')
            ->assertSee('lang="my"', false)->assertSee('MOMO')->assertDontSee('PROMPTPAY');
    }

    public function test_can_be_turned_off(): void
    {
        Setting::put('geo.auto_locale', '0');
        Setting::put('geo.auto_payment', '0');

        $this->fromCloudflare('TH')->get('/')->assertSee('lang="en"', false)->assertSee('KBZPAY');
    }
}
