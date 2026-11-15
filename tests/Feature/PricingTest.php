<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PriceChangeRequest;
use App\Models\ServiceCostChange;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\OrderService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('pricing.rounding', '100');
        Setting::put('pricing.approval_percent', '20');
        Setting::put('pricing.loss_action', 'pause');
        UserGroup::query()->delete();
        UserGroup::create(['name' => 'Customer', 'slug' => 'customer', 'is_default' => true, 'sort' => 0, 'markup_type' => 'percent', 'markup_value' => 20, 'min_profit' => 300]);
        UserGroup::create(['name' => 'Reseller', 'slug' => 'reseller', 'sort' => 1, 'markup_type' => 'fixed', 'markup_value' => 150, 'min_profit' => 0]);
    }

    public function test_rule_prices_use_markup_min_profit_and_rounding(): void
    {
        $service = $this->imeiService(['cost_price' => 1000, 'pricing_mode' => 'auto']);
        $prices = app(PricingService::class)->compute($service);

        // Customer: 20% of 1000 = 200 < min 300 → 1300; Reseller: 1000 + 150 = 1150 → rounded up to 1200
        $this->assertEquals(1300, $prices['default']);
        $this->assertEquals(1200, array_values($prices['groups'])[0]);
    }

    public function test_cost_change_is_logged_and_big_moves_wait_for_approval(): void
    {
        $pricing = app(PricingService::class);
        $service = $this->imeiService(['cost_price' => 1000, 'default_price' => 1300, 'pricing_mode' => 'auto']);
        $pricing->reprice($service, null, approvedBy: User::factory()->create());

        $pricing->setCost($service->fresh(), 1050, null, 'sync'); // 1050 + 300 = 1350 → 1400 (+7.7%): applied
        $this->assertEquals(1400, (float) $service->fresh()->default_price);
        $this->assertSame(1, ServiceCostChange::count());

        $pricing->setCost($service->fresh(), 2000, null, 'sync'); // ~+80%: waits
        $req = PriceChangeRequest::where('status', 'pending')->firstOrFail();
        $before = (float) $service->fresh()->default_price;
        $this->assertNotEquals((float) $req->new_prices['default'], $before);

        $pricing->approve($req, User::factory()->create());
        $this->assertEquals((float) $req->new_prices['default'], (float) $service->fresh()->default_price);
        $this->assertSame(2, ServiceCostChange::count());
    }

    public function test_selling_below_cost_pauses_orders_and_reopens_when_fixed(): void
    {
        $pricing = app(PricingService::class);
        $service = $this->imeiService(['cost_price' => 700, 'default_price' => 1000, 'pricing_mode' => 'manual']);

        $pricing->setCost($service, 1200, null, 'sync');
        $this->assertTrue($service->fresh()->sales_paused);

        try {
            app(OrderService::class)->place($this->customer(), $service->fresh(), [['imei' => '356938035643809']]);
            $this->fail('Order placed on a paused service');
        } catch (RuntimeException) {
        }
        $this->assertSame(0, Order::count());

        $pricing->setCost($service->fresh(), 800, null, 'sync');
        $this->assertFalse($service->fresh()->sales_paused);
    }

    public function test_placed_order_keeps_its_price_and_cost(): void
    {
        $service = $this->imeiService(['cost_price' => 700, 'default_price' => 1000]);
        $order = app(OrderService::class)->place($this->customer(), $service, [['imei' => '356938035643809']])->first();

        app(PricingService::class)->setCost($service, 1500, null, 'sync');

        $this->assertEquals(1000, (float) $order->fresh()->price);
        $this->assertEquals(700, (float) $order->fresh()->cost);
    }
}
