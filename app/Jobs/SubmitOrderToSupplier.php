<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\Suppliers\SupplierException;
use App\Services\Suppliers\SupplierManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Send an API-mode order to its supplier. On failure the order falls back to the manual queue. */
class SubmitOrderToSupplier implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // retries are counted on the order itself

    public function __construct(public int $orderId) {}

    public function handle(OrderService $orders, SupplierManager $suppliers): void
    {
        $order = Order::with(['supplier', 'service'])->find($this->orderId);

        if (! $order || ! $order->isOpen() || $order->processing_mode !== 'api' || $order->supplier_order_id) {
            return;
        }

        $supplier = $order->supplier;

        if (! $supplier || ! $supplier->isUsable()) {
            $orders->apiFailed($order, 'Supplier is not configured or disabled.');

            return;
        }

        $maxAttempts = (int) config('unlockhub.api.max_attempts', 3);
        $order->increment('api_attempts');

        try {
            $reference = $suppliers->driverFor($supplier)->placeOrder($supplier, $order);
        } catch (SupplierException $e) {
            if ($order->api_attempts < $maxAttempts && $this->isTemporary($e)) {
                self::dispatch($order->id)->delay(now()->addMinute());

                return;
            }
            $orders->apiFailed($order, $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $orders->apiFailed($order, 'Unexpected error: '.$e->getMessage());

            return;
        }

        $order->forceFill([
            'supplier_order_id' => $reference,
            'status' => Order::IN_PROCESS,
            'api_next_check_at' => now()->addMinutes((int) config('unlockhub.api.check_every_minutes', 5)),
        ])->save();
    }

    private function isTemporary(SupplierException $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'timeout')
            || str_contains(strtolower($e->getMessage()), 'connect');
    }
}
