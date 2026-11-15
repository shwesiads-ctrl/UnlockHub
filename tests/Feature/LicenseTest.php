<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private const SITE = 'https://shop1.com';

    protected function setUp(): void
    {
        parent::setUp();
        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        config([
            'license.public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'license.server' => 'https://owner.myominaung.xyz',
            'app.url' => self::SITE,
        ]);
        @unlink(storage_path('app/private/license.json'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/private/license.json'));
        parent::tearDown();
    }

    private function answer(string $status, array $extra = [], ?string $secret = null): array
    {
        $payload = $extra + [
            'v' => 1, 'status' => $status, 'message' => $status === 'active' ? '' : 'Not active', 'serial' => 'UHUB-AAAA-BBBB-CCCC-DDDD',
            'domain' => 'shop1.com', 'customer' => 'Ko Aung', 'plan' => 'Standard', 'expires_at' => null,
            'issued_at' => time(), 'valid_until' => $status === 'active' ? time() + 7 * 86400 : time(),
        ];
        $json = json_encode($payload);

        return ['ok' => $status === 'active', 'payload' => base64_encode($json), 'signature' => base64_encode(sodium_crypto_sign_detached($json, $secret ?? $this->secret))];
    }

    private function fakeServer(string $status, array $extra = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory); // a later fake must replace the earlier one
        Http::fake(['owner.myominaung.xyz/*' => Http::response($this->answer($status, $extra), $status === 'active' ? 200 : 403)]);
    }

    private function serverDown(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(fn () => throw new ConnectionException('down'));
    }

    private function owner(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin', 'staff_role' => 'owner'])->save();
        $u = $u->fresh(); // reload DB defaults (status = active)

        return $u->fresh();
    }

    public function test_lock_is_off_without_public_key(): void
    {
        config(['license.public_key' => '']);
        $this->get(self::SITE.'/')->assertOk();
    }

    public function test_local_install_never_needs_a_key(): void
    {
        config(['app.url' => 'http://localhost']);
        $this->get('http://localhost/')->assertOk();
    }

    public function test_no_key_locks_site_but_owner_can_activate(): void
    {
        Http::preventStrayRequests();
        $this->get(self::SITE.'/')->assertStatus(503)->assertSee('Site not available');
        $this->get(self::SITE.'/login')->assertOk();

        $owner = $this->owner();
        $this->actingAs($owner)->get(self::SITE.'/admin')->assertRedirect(route('admin.license'));
        $this->actingAs($owner)->get(self::SITE.'/admin/license')->assertOk()->assertSee('Not licensed');

        $this->fakeServer('active');
        $this->actingAs($owner)->post(self::SITE.'/admin/license', ['serial' => 'uhub-aaaa-bbbb-cccc-dddd'])
            ->assertRedirect(route('admin.license'))->assertSessionHas('success');

        $this->assertSame('UHUB-AAAA-BBBB-CCCC-DDDD', Setting::get('license.key') ?? setting('license.key'));
        $this->get(self::SITE.'/')->assertOk();
        Http::assertSent(fn ($r) => $r['domain'] === 'shop1.com' && str_ends_with($r->url(), '/api/v1/activate'));
    }

    public function test_suspended_answer_locks_the_site(): void
    {
        Setting::put('license.key', 'UHUB-AAAA-BBBB-CCCC-DDDD');
        $this->fakeServer('active');
        LicenseService::refresh();
        $this->get(self::SITE.'/')->assertOk();

        $this->fakeServer('suspended');
        LicenseService::refresh();
        $this->assertSame('suspended', LicenseService::status()['reason']);
        $this->get(self::SITE.'/')->assertStatus(503);
        $this->postJson(self::SITE.'/api/index.php', ['action' => 'accountinfo'])->assertStatus(503);
    }

    public function test_key_bound_to_another_domain_is_refused(): void
    {
        Setting::put('license.key', 'UHUB-AAAA-BBBB-CCCC-DDDD');
        $this->fakeServer('active', ['domain' => 'other.com']);
        LicenseService::refresh();

        $this->assertFalse(LicenseService::valid());
        $this->assertSame('domain', LicenseService::status()['reason']);
    }

    public function test_subdomains_and_www_are_covered(): void
    {
        $this->assertTrue(LicenseService::covers('shop1.com', 'www.shop1.com'));
        $this->assertTrue(LicenseService::covers('shop1.com', 'reseller.shop1.com'));
        $this->assertFalse(LicenseService::covers('shop1.com', 'evilshop1.com'));
        $this->assertFalse(LicenseService::covers('shop1.com', 'shop1.com.evil.net'));
    }

    public function test_forged_answer_is_rejected(): void
    {
        Setting::put('license.key', 'UHUB-AAAA-BBBB-CCCC-DDDD');
        $fake = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        Http::fake(['owner.myominaung.xyz/*' => Http::response($this->answer('active', [], $fake))]);

        $r = LicenseService::refresh();
        $this->assertFalse($r['ok']);
        $this->assertFalse(LicenseService::valid());
    }

    public function test_offline_grace_then_lock(): void
    {
        Setting::put('license.key', 'UHUB-AAAA-BBBB-CCCC-DDDD');
        // Last good answer 10 days ago, valid 7 days → still inside the 7-day grace
        $this->fakeServer('active', ['issued_at' => time() - 10 * 86400, 'valid_until' => time() - 3 * 86400]);
        LicenseService::refresh();
        $this->serverDown();
        $this->assertTrue(LicenseService::valid());

        // 20 days ago → grace is over
        $this->fakeServer('active', ['issued_at' => time() - 20 * 86400, 'valid_until' => time() - 13 * 86400]);
        LicenseService::refresh();
        $this->serverDown();
        $this->assertSame('offline', LicenseService::status()['reason']);
        $this->get(self::SITE.'/')->assertStatus(503);
    }

    public function test_staff_cannot_open_license_page(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin', 'staff_role' => 'support'])->save();
        $u = $u->fresh();
        Setting::put('license.key', 'UHUB-AAAA-BBBB-CCCC-DDDD');
        $this->fakeServer('active');
        LicenseService::refresh();

        $this->actingAs($u)->get(self::SITE.'/admin/license')->assertForbidden();
    }
}
