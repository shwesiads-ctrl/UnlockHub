<?php

namespace App\Console\Commands;

use App\Jobs\SubmitOrderToSupplier;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Suppliers\SupplierException;
use App\Services\Suppliers\SupplierManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * Runs every minute (scheduler):
 *  1. Ask suppliers for results of In Process API orders that are due for a check.
 *  2. Re-send API orders that never reached the supplier (e.g. queue worker was down).
 */
class CheckApiOrders extends Command
{
    protected $signature = 'orders:check-api {--limit=100 : Max orders to check per run}';

    protected $description = 'Fetch results for API orders from suppliers';

    public function handle(OrderService $orders, SupplierManager $suppliers): int
    {
        $every = (int) config('unlockhub.api.check_every_minutes', 5);
        $checked = $done = $failed = 0;

        $due = Order::with(['supplier', 'service', 'user'])
            ->where('processing_mode', 'api')
            ->where('status', Order::IN_PROCESS)
            ->whereNotNull('supplier_order_id')
            ->where(fn ($q) => $q->whereNull('api_next_check_at')->orWhere('api_next_check_at', '<=', now()))
            ->orderBy('api_next_check_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($due as $order) {
            $checked++;

            if (! $order->supplier?->isUsable()) {
                $order->forceFill(['api_next_check_at' => now()->addMinutes($every * 3)])->save();
                continue;
            }

            try {
                $r = $suppliers->driverFor($order->supplier)->checkOrder($order->supplier, $order);
            } catch (SupplierException $e) {
                $order->forceFill(['api_error' => $e->getMessage(), 'api_next_check_at' => now()->addMinutes($every)])->save();
                $failed++;
                continue;
            } catch (Throwable $e) {
                report($e);
                $order->forceFill(['api_next_check_at' => now()->addMinutes($every)])->save();
                $failed++;
                continue;
            }

            try {
                match ($r['status']) {
                    'success' => $orders->complete($order, (string) $r['result']),
                    'rejected' => $orders->reject($order, (string) $r['result']),
                    default => $order->forceFill([
                        'api_error' => null,
                        // Back off for orders that take days: check less often after the first day
                        'api_next_check_at' => now()->addMinutes($order->created_at->lt(now()->subDay()) ? $every * 6 : $every),
                    ])->save(),
                };
                if (in_array($r['status'], ['success', 'rejected'], true)) {
                    $done++;
                }
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        // Orders still waiting to be sent (worker was down, or a temporary error)
        $maxAttempts = (int) config('unlockhub.api.max_attempts', 3);
        $stuck = Order::where('processing_mode', 'api')
            ->where('status', Order::PENDING)
            ->whereNull('supplier_order_id')
            ->where('api_attempts', '<', $maxAttempts)
            ->where('updated_at', '<=', now()->subMinutes(3))
            ->limit(50)->pluck('id');

        foreach ($stuck as $id) {
            SubmitOrderToSupplier::dispatch($id);
        }

        $this->info("Checked {$checked}, finished {$done}, errors {$failed}, re-sent {$stuck->count()}.");

        return self::SUCCESS;
    }
}
