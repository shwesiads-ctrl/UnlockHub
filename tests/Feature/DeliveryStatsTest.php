<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ServiceDeliveryStat;
use App\Models\Setting;
use App\Services\DeliveryStatsService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryStatsTest extends TestCase
{
    use RefreshDatabase;

    private function finished(int $count, int $minutes): void
    {
        $service = $this->imeiService();
        $user = $this->customer(1000000);
        for ($i = 0; $i < $count; $i++) {
            $o = app(OrderService::class)->place($user, $service, [['imei' => '35693803564'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)]])->first();
            $o->forceFill(['status' => Order::SUCCESS, 'created_at' => now()->subMinutes($minutes + $i), 'completed_at' => now()->subMinutes($i)])->save();
        }
    }

    public function test_stats_and_reliability(): void
    {
        Setting::put('delivery.min_samples', '10');
        $this->finished(4, 30);

        app(DeliveryStatsService::class)->compute();
        $stat = ServiceDeliveryStat::where('supplier_key', 0)->where('window_days', 30)->firstOrFail();

        $this->assertSame(4, $stat->samples);
        $this->assertSame(30, $stat->median_minutes);
        $this->assertFalse($stat->reliable()); // 4 < 10: not shown as an estimate
    }

    public function test_percentiles(): void
    {
        $this->assertSame(3, DeliveryStatsService::percentile([1, 2, 3, 4, 5], 50));
        $this->assertSame(2, DeliveryStatsService::percentile([1, 2, 3, 4, 5], 25));
        $this->assertSame('1 h 35 min', DeliveryStatsService::human(95));
        $this->assertSame('2 d 2 h', DeliveryStatsService::human(3000));
    }

    public function test_overdue_orders_alert_once(): void
    {
        $service = $this->imeiService(['max_delivery_minutes' => 60]);
        $order = app(OrderService::class)->place($this->customer(), $service, [['imei' => '356938035643809']])->first();
        $order->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->assertCount(1, app(DeliveryStatsService::class)->overdue());
        $this->assertCount(0, app(DeliveryStatsService::class)->overdue());
    }
}
