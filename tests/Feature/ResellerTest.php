<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ResellerProfile;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AffiliateService;
use App\Services\OrderService;
use App\Services\ResellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ResellerTest extends TestCase
{
    use RefreshDatabase;

    private function reseller(float $balance = 100000, float $markup = 20): User
    {
        $r = $this->customer($balance);
        ResellerProfile::create(['user_id' => $r->id, 'status' => 'approved', 'shop_name' => 'Shop', 'subdomain' => 'myshop', 'markup_type' => 'percent', 'markup_value' => $markup]);

        return $r->fresh();
    }

    public function test_customer_pays_reseller_price_and_reseller_earns_on_success_only(): void
    {
        Setting::put('reseller.rounding', '1');
        $reseller = $this->reseller();
        $service = $this->imeiService(); // site price 1000
        $customer = app(ResellerService::class)->createCustomer($reseller, ['name' => 'C', 'username' => 'cust1', 'password' => 'secret123']);
        app(ResellerService::class)->transfer($reseller, $customer, 5000);

        $this->assertEquals(1200, (float) $service->priceFor($customer->fresh())); // 1000 + 20%

        $orders = app(OrderService::class);
        $ok = $orders->place($customer->fresh(), $service, [['imei' => '356938035643809']])->first();
        $bad = $orders->place($customer->fresh(), $service, [['imei' => '490154203237518']])->first();
        $this->assertEquals(1000, (float) $ok->reseller_price);
        $this->assertEquals(2600, (float) $customer->fresh()->balance);

        $orders->complete($ok, 'CODE');
        $this->assertTrue($ok->fresh()->reseller_paid);
        $this->assertSame('pending', $bad->fresh()->status);
    }

    public function test_profit_paid_once_and_reject_refunds_customer(): void
    {
        Setting::put('reseller.rounding', '1');
        $reseller = $this->reseller(100000);
        $service = $this->imeiService();
        $customer = app(ResellerService::class)->createCustomer($reseller, ['name' => 'C', 'username' => 'cust2', 'password' => 'secret123']);
        app(ResellerService::class)->transfer($reseller, $customer, 5000);
        $orders = app(OrderService::class);

        $ok = $orders->place($customer->fresh(), $service, [['imei' => '356938035643809']])->first();
        $orders->complete($ok, 'CODE');
        $bad = $orders->place($customer->fresh(), $service, [['imei' => '490154203237518']])->first();
        $orders->reject($bad, 'Not found');

        $this->assertSame(1, WalletTransaction::where('type', 'reseller_profit')->count());
        $this->assertEquals(95000 + 200, (float) $reseller->fresh()->balance);
        $this->assertEquals(3800, (float) $customer->fresh()->balance);
        $this->assertSame(0, (int) Order::find($ok->id)->points);
    }

    public function test_reseller_cannot_touch_other_resellers_customers(): void
    {
        $a = $this->reseller();
        $b = $this->customer();
        ResellerProfile::create(['user_id' => $b->id, 'status' => 'approved', 'shop_name' => 'B']);
        $customer = app(ResellerService::class)->createCustomer($a, ['name' => 'C', 'username' => 'cust3', 'password' => 'secret123']);

        $this->expectException(RuntimeException::class);
        app(ResellerService::class)->transfer($b->fresh(), $customer, 100);
    }

    public function test_reseller_panel_404_for_foreign_customer(): void
    {
        $this->withoutVite();
        $a = $this->reseller();
        $b = $this->customer();
        ResellerProfile::create(['user_id' => $b->id, 'status' => 'approved', 'shop_name' => 'B']);
        $customer = app(ResellerService::class)->createCustomer($a, ['name' => 'C', 'username' => 'cust4', 'password' => 'secret123']);

        $this->actingAs($b->fresh())->get(route('reseller.customers.show', $customer))->assertNotFound();
        $this->actingAs($this->customer())->get(route('reseller.dashboard'))->assertRedirect(route('account.reseller.apply'));
    }

    public function test_commission_and_withdrawal(): void
    {
        Setting::put('affiliate.enabled', '1');
        Setting::put('affiliate.percent', '10');
        Setting::put('affiliate.basis', 'sale');
        Setting::put('affiliate.withdraw_min', '50');
        $referrer = $this->customer(0);
        $buyer = $this->customer(10000);
        $buyer->forceFill(['referred_by' => $referrer->id])->save();
        $orders = app(OrderService::class);

        $o = $orders->place($buyer->fresh(), $this->imeiService(), [['imei' => '356938035643809']])->first();
        $orders->complete($o, 'CODE');
        app(AffiliateService::class)->payCommission($o->fresh()); // second call: no double pay

        $this->assertEquals(100, (float) $referrer->fresh()->balance);
        $this->assertEquals(100, AffiliateService::withdrawable($referrer->fresh()));

        $w = app(AffiliateService::class)->requestWithdrawal($referrer->fresh(), 100, 'KBZPay', '09xxx');
        $this->assertEquals(0, (float) $referrer->fresh()->balance);
        app(AffiliateService::class)->reject($w, $this->customer(), 'Wrong number');
        $this->assertEquals(100, (float) $referrer->fresh()->balance);

        // Top-up money is not withdrawable
        $this->assertEquals(100, AffiliateService::withdrawable($referrer->fresh()));
    }

    public function test_shop_domain_branding(): void
    {
        Setting::put('reseller.base_domain', 'example.test');
        $this->reseller();
        Route::middleware('web')->get('/_shop', fn () => setting('site.name', 'Main'));

        $this->get('http://myshop.example.test/_shop')->assertSee('Shop');
        $this->get('http://example.test/_shop')->assertSee('Main');
        $this->get('http://nope.example.test/_shop')->assertNotFound();
    }
}
