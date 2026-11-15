<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\Setting;
use App\Services\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest(): void
    {
        $this->get('/manifest.webmanifest')->assertOk()
            ->assertJsonPath('display', 'standalone')
            ->assertJsonCount(3, 'icons');
    }

    public function test_private_pages_are_never_stored(): void
    {
        $this->withoutVite();
        $res = $this->actingAs($this->customer())->get(route('account.statement'));

        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
    }

    public function test_service_worker_only_caches_static_files(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("if (req.method !== 'GET') return;", $sw);
        $this->assertStringContainsString("url.pathname.startsWith('/build/')", $sw);
        $this->assertStringNotContainsString("cache.put(req, res.clone());\n  }\n  //", $sw);
    }

    public function test_push_subscription_rules(): void
    {
        WebPush::generateKeys();
        $user = $this->customer();

        $this->actingAs($user)->postJson('/account/push', ['endpoint' => 'https://evil.example.com/x'])->assertStatus(422);
        $this->actingAs($user)->postJson('/account/push', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertOk();
        $this->assertSame(1, PushSubscription::count());

        // Turned off by admin → no new subscriptions
        Setting::put('push.enabled', '0');
        $this->actingAs($user)->postJson('/account/push', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/def'])->assertNotFound();
    }

    public function test_latest_shows_only_own_notification(): void
    {
        $alice = $this->customer();
        $bob = $this->customer();
        $alice->notifications()->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'data' => ['title' => 'Alice order done', 'body' => 'b']]);

        $this->actingAs($bob)->getJson('/account/push/latest')->assertOk()->assertJsonMissing(['title' => 'Alice order done']);
        $this->actingAs($alice)->getJson('/account/push/latest')->assertJsonPath('title', 'Alice order done');
    }
}
