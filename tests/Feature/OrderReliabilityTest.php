<?php

namespace Tests\Feature;

use App\Exceptions\DuplicateOrderException;
use App\Models\Order;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private const IMEI = '356938035643809';

    public function test_same_request_key_places_and_charges_once(): void
    {
        $user = $this->customer(5000);
        $service = $this->imeiService();
        $orders = app(OrderService::class);

        $first = $orders->place($user, $service, [['imei' => self::IMEI]], null, ['key' => 'web:abc']);
        $again = $orders->place($user, $service, [['imei' => self::IMEI]], null, ['key' => 'web:abc']);

        $this->assertSame($first->first()->id, $again->first()->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, WalletTransaction::where('type', 'order')->count());
        $this->assertEquals(4000, (float) $user->fresh()->balance);
    }

    public function test_open_duplicate_imei_is_warned_then_allowed_when_confirmed(): void
    {
        $user = $this->customer();
        $service = $this->imeiService(['duplicate_policy' => 'warn']);
        $orders = app(OrderService::class);

        $orders->place($user, $service, [['imei' => self::IMEI]]);

        try {
            $orders->place($user, $service, [['imei' => self::IMEI]]);
            $this->fail('Expected a duplicate warning.');
        } catch (DuplicateOrderException $e) {
            $this->assertFalse($e->blocked);
            $this->assertSame(self::IMEI, $e->existing->first()->imei);
        }
        $this->assertSame(1, Order::count());

        $orders->place($user, $service, [['imei' => self::IMEI]], null, ['allow_duplicates' => true]);
        $this->assertSame(2, Order::count());
    }

    public function test_block_policy_refuses_even_when_confirmed(): void
    {
        $user = $this->customer();
        $service = $this->imeiService(['duplicate_policy' => 'block']);
        $orders = app(OrderService::class);

        $orders->place($user, $service, [['imei' => self::IMEI]]);

        $this->expectException(DuplicateOrderException::class);
        $orders->place($user, $service, [['imei' => self::IMEI]], null, ['allow_duplicates' => true]);
    }

    public function test_finished_orders_do_not_count_as_duplicates(): void
    {
        $user = $this->customer();
        $service = $this->imeiService(['duplicate_policy' => 'block']);
        $orders = app(OrderService::class);

        $order = $orders->place($user, $service, [['imei' => self::IMEI]])->first();
        $orders->reject($order, 'Not found');

        $orders->place($user, $service, [['imei' => self::IMEI]]);
        $this->assertSame(2, Order::count());
    }

    public function test_price_change_does_not_touch_placed_orders(): void
    {
        $user = $this->customer();
        $service = $this->imeiService();
        $order = app(OrderService::class)->place($user, $service, [['imei' => self::IMEI]])->first();

        $service->update(['default_price' => 2500, 'cost_price' => 2000]);

        $order->refresh();
        $this->assertEquals(1000, (float) $order->price);
        $this->assertEquals(700, (float) $order->cost);
    }
}
