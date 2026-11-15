<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ServiceDeliveryStat;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;

/**
 * How long orders really take (placed → success), for the last 7 and 30 days,
 * per service and per supplier. Shown to customers only as an estimate, and only
 * when there are enough finished orders.
 */
class DeliveryStatsService
{
    public const WINDOWS = [7, 30];

    public function compute(): int
    {
        $now = now();
        $rows = [];

        foreach (self::WINDOWS as $days) {
            $groups = [];
            Order::query()
                ->where('status', Order::SUCCESS)
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $now->copy()->subDays($days))
                ->select(['id', 'service_id', 'supplier_id', 'created_at', 'completed_at'])
                ->lazyById(2000)
                ->each(function (Order $o) use (&$groups) {
                    $min = max(0, (int) round($o->created_at->diffInSeconds($o->completed_at, true) / 60));
                    $groups[$o->service_id][0][] = $min;                         // all together
                    if ($o->supplier_id) {
                        $groups[$o->service_id][$o->supplier_id][] = $min;       // per supplier
                    }
                });

            foreach ($groups as $serviceId => $bySupplier) {
                foreach ($bySupplier as $supplierKey => $mins) {
                    sort($mins);
                    $rows[] = [
                        'service_id' => $serviceId,
                        'supplier_key' => $supplierKey,
                        'window_days' => $days,
                        'samples' => count($mins),
                        'avg_minutes' => (int) round(array_sum($mins) / count($mins)),
                        'median_minutes' => self::percentile($mins, 50),
                        'p25_minutes' => self::percentile($mins, 25),
                        'p75_minutes' => self::percentile($mins, 75),
                        'p90_minutes' => self::percentile($mins, 90),
                        'computed_at' => $now,
                    ];
                }
            }
        }

        DB::transaction(function () use ($rows) {
            ServiceDeliveryStat::query()->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                ServiceDeliveryStat::insert($chunk);
            }
        });

        return count($rows);
    }

    /** Open orders older than their service's alert time. Alerts once per order. */
    public function overdue(bool $markAlerted = true): \Illuminate\Support\Collection
    {
        $orders = Order::query()
            ->join('services', 'services.id', '=', 'orders.service_id')
            ->whereIn('orders.status', [Order::PENDING, Order::IN_PROCESS])
            ->whereNotNull('services.max_delivery_minutes')
            ->whereNull('orders.overdue_alerted_at')
            ->get(['orders.id', 'orders.order_no', 'orders.service_name', 'orders.created_at', 'services.max_delivery_minutes'])
            ->filter(fn ($o) => $o->created_at->lt(now()->subMinutes((int) $o->max_delivery_minutes)))
            ->values();

        if ($markAlerted && $orders->isNotEmpty()) {
            Order::whereIn('id', $orders->pluck('id'))->update(['overdue_alerted_at' => now()]);

            $lines = $orders->take(15)->map(fn ($o) => '#'.$o->order_no.' · '.Notify::e(\Illuminate\Support\Str::limit($o->service_name, 40)).' · '.self::human((int) $o->created_at->diffInMinutes(now(), true)))->implode("\n");
            Notify::admin('overdue', "⏰ <b>{$orders->count()} order(s) past their delivery time</b>\n{$lines}\n".route('admin.reports.delivery'));
        }

        return $orders;
    }

    public static function percentile(array $sorted, int $p): int
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0;
        }
        $rank = ($p / 100) * ($n - 1);
        $lo = (int) floor($rank);
        $hi = (int) ceil($rank);

        return (int) round($sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($rank - $lo));
    }

    /** 0 → "under 1 min", 95 → "1 h 35 min", 3000 → "2 d 2 h" */
    public static function human(int $minutes): string
    {
        if ($minutes < 1) {
            return 'under 1 min';
        }
        if ($minutes < 60) {
            return $minutes.' min';
        }
        if ($minutes < 1440) {
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;

            return $h.' h'.($m && $h < 10 ? ' '.$m.' min' : '');
        }
        $d = intdiv($minutes, 1440);
        $h = intdiv($minutes % 1440, 60);

        return $d.' d'.($h ? ' '.$h.' h' : '');
    }
}
