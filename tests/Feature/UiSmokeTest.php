<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every page opens in every language without errors, untranslated keys or broken layout markers. */
class UiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC = ['/', '/services', '/login', '/register', '/forgot-password', '/track-order', '/contact', '/kb', '/blog', '/downloads', '/testimonials'];

    private const ACCOUNT = [
        '/account', '/account/profile', '/account/orders', '/account/funds', '/account/statement', '/account/points',
        '/account/notifications', '/account/api', '/account/security', '/account/favorites', '/account/tickets', '/account/tickets/new',
    ];

    public function test_public_pages_in_every_language(): void
    {
        $this->imeiService();
        foreach (array_keys(config('unlockhub.locales')) as $locale) {
            foreach (self::PUBLIC as $url) {
                $res = $this->withSession(['locale' => $locale])->get($url);
                $this->assertContains($res->status(), [200], "{$url} in {$locale} returned {$res->status()}");
                $res->assertSee('lang="'.$locale.'"', false);
            }
        }
    }

    public function test_account_pages_in_every_language(): void
    {
        $user = $this->customer(50000);
        foreach (array_keys(config('unlockhub.locales')) as $locale) {
            foreach (self::ACCOUNT as $url) {
                $res = $this->actingAs($user)->withSession(['locale' => $locale])->get($url);
                $this->assertSame(200, $res->status(), "{$url} in {$locale} returned {$res->status()}");
            }
        }
    }

    public function test_translated_text_actually_shows(): void
    {
        $this->withSession(['locale' => 'th'])->get('/login')->assertSee('เข้าสู่ระบบด้วยชื่อผู้ใช้หรืออีเมล');
        $this->withSession(['locale' => 'zh'])->get('/services')->assertSee('交付时间为估算值。');
        $this->withSession(['locale' => 'vi'])->get('/track-order')->assertSee('Tra cứu đơn hàng');
        $this->withSession(['locale' => 'lo'])->get('/contact')->assertSee('ຕິດຕໍ່ພວກເຮົາ');
        $this->actingAs($this->customer())->withSession(['locale' => 'my'])->get('/account')->assertSee('မင်္ဂလာပါ');
    }
}
