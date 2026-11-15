<?php

namespace App\Services;

use App\Exceptions\DuplicateOrderException;
use App\Exceptions\InsufficientBalanceException;
use App\Jobs\SubmitOrderToSupplier;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BatchFinished;
use App\Notifications\OrderUpdated;
use App\Support\Notify;
use Illuminate\Support\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Order lifecycle: place → (in process) → success | rejected | cancelled.
 * Credit is taken when the order is placed and refunded on reject / cancel.
 * Points are added on success.
 */
class OrderService
{
    public function __construct(
        private WalletService $wallet,
        private PointService $points,
    ) {}

    /**
     * @param  array<int, array<string, string>>  $rows  one input array per order (bulk = many rows)
     * @param  array{key?: ?string, source?: string, allow_duplicates?: bool}  $options
     *         key: request token (form token / API REFERENCE). Same key again → the first
     *              request's orders are returned; nothing new is placed or charged.
     *         allow_duplicates: user confirmed "order anyway" (ignored when the service blocks duplicates)
     * @return Collection<int, Order>
     *
     * @throws DuplicateOrderException|InsufficientBalanceException
     */
    public function place(User $user, Service $service, array $rows, ?string $note = null, array $options = []): Collection
    {
        if ($rows === []) {
            throw new RuntimeException('Nothing to order.');
        }

        if ($service->sales_paused) {
            throw new RuntimeException('This service is temporarily unavailable. Please try again later or contact support.');
        }

        $key = filled($options['key'] ?? null) ? mb_substr((string) $options['key'], 0, 100) : null;

        try {
            return $this->placeOnce($user, $service, $rows, $note, $key, $options);
        } catch (UniqueConstraintViolationException $e) {
            // Two identical requests raced past each other: the other one won, return its orders
            if ($key && ($done = $this->replay($user, $key))) {
                return $done;
            }
            throw $e;
        }
    }

    /** Orders created by an earlier request with this key, or null. */
    public function replay(User $user, string $key): ?Collection
    {
        $req = OrderRequest::where('user_id', $user->id)->where('key', $key)->first();

        return $req ? Order::whereIn('id', $req->order_ids)->orderBy('id')->get() : null;
    }

    private function placeOnce(User $user, Service $service, array $rows, ?string $note, ?string $key, array $options): Collection
    {
        $price = round((float) $service->priceFor($user), 2);
        $cost = round((float) $service->cost_price, 2);
        $points = $service->pointsFor((string) $price);
        $total = round($price * count($rows), 2);
        $useApi = $service->usesApi();

        $replayed = null;
        $orders = DB::transaction(function () use ($user, $service, $rows, $note, $price, $cost, $points, $total, $useApi, $key, $options, &$replayed) {
            // The user row lock serialises this user's orders: checks below can't race
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($key && ($replayed = $this->replay($user, $key))) {
                return $replayed;
            }

            $this->checkDuplicates($user, $service, $rows, (bool) ($options['allow_duplicates'] ?? false));

            if ((float) $locked->balance < $total) {
                throw new InsufficientBalanceException(
                    'Not enough balance. This order costs '.money($total).' and your wallet has '.money($locked->balance).'.'
                );
            }

            $batch = count($rows) > 1 ? (string) Str::uuid() : null;
            $created = collect();

            foreach ($rows as $input) {
                $order = Order::create([
                    'batch_id' => $batch,
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'input' => $input,
                    'imei' => $this->mainIdentifier($service, $input),
                    'user_note' => $note,
                    'price' => $price,
                    'cost' => $cost,
                    'points' => $points,
                    'status' => Order::PENDING,
                    'processing_mode' => $useApi ? 'api' : 'manual',
                    'supplier_id' => $useApi ? $service->supplier_id : null,
                ]);

                $this->wallet->debit($user, $price, 'order', $order, "Order #{$order->order_no} · {$service->name}");
                $created->push($order);
            }

            if ($key) {
                OrderRequest::create([
                    'user_id' => $user->id, 'key' => $key,
                    'source' => $options['source'] ?? 'web', 'order_ids' => $created->pluck('id')->all(),
                ]);
            }

            return $created;
        });

        if ($replayed) {
            return $orders; // already submitted / notified the first time
        }

        if ($useApi) {
            foreach ($orders as $order) {
                SubmitOrderToSupplier::dispatch($order->id)->afterCommit();
            }
        } else {
            $first = $orders->first();
            Notify::admin('manual_order', '🛠 <b>New manual order'.($orders->count() > 1 ? 's ×'.$orders->count() : '').'</b>'
                ."\n".Notify::e($service->name)
                ."\nUser: ".Notify::e($user->username).' · '.money($total)
                ."\n".route('admin.orders.show', $first));
        }

        return $orders;
    }

    /** Open orders of this user for the same service + identifier. */
    public function openDuplicates(User $user, Service $service, array $rows): Collection
    {
        $ids = collect($rows)->map(fn ($r) => $this->mainIdentifier($service, $r))->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Order::where('user_id', $user->id)
            ->where('service_id', $service->id)
            ->whereIn('imei', $ids)
            ->whereIn('status', [Order::PENDING, Order::IN_PROCESS])
            ->orderBy('id')
            ->get(['id', 'order_no', 'imei', 'status', 'created_at']);
    }

    private function checkDuplicates(User $user, Service $service, array $rows, bool $allow): void
    {
        $policy = $service->duplicate_policy ?? 'warn';

        if ($policy === 'allow' || ($policy === 'warn' && $allow)) {
            return;
        }

        $dupes = $this->openDuplicates($user, $service, $rows);

        if ($dupes->isNotEmpty()) {
            throw new DuplicateOrderException($dupes, $policy === 'block');
        }
    }

    public function markInProcess(Order $order): void
    {
        $this->locked($order, function (Order $o) {
            if ($o->status !== Order::PENDING) {
                return;
            }
            $o->forceFill(['status' => Order::IN_PROCESS, 'processed_by' => auth()->id() ?? $o->processed_by])->save();
        });
    }

    public function complete(Order $order, string $result, ?string $adminNote = null): void
    {
        $this->locked($order, function (Order $o) use ($result, $adminNote) {
            $this->assertOpen($o);

            $o->forceFill([
                'status' => Order::SUCCESS,
                'result' => $result,
                'admin_note' => $adminNote ?? $o->admin_note,
                'processed_by' => auth()->id() ?? $o->processed_by,
                'completed_at' => now(),
            ])->save();

            if ($o->points > 0 && ! $o->points_awarded) {
                $this->points->add($o->user, $o->points, 'order', $o, "Order #{$o->order_no} success");
                $o->forceFill(['points_awarded' => true])->save();
            }

            $this->points->rewardReferrer($o);

            ActivityLog::record('order.success', $o);
            $this->notifyFinished($o);
        });
    }

    public function reject(Order $order, string $reason, ?string $adminNote = null): void
    {
        $this->locked($order, function (Order $o) use ($reason, $adminNote) {
            $this->assertOpen($o);

            $o->forceFill([
                'status' => Order::REJECTED,
                'result' => $reason,
                'admin_note' => $adminNote ?? $o->admin_note,
                'processed_by' => auth()->id() ?? $o->processed_by,
                'completed_at' => now(),
            ])->save();

            $this->refund($o, "Refund · order #{$o->order_no} rejected");
            ActivityLog::record('order.rejected', $o, ['reason' => $reason]);
            $this->notifyFinished($o);
        });
    }

    public function cancelByUser(Order $order): void
    {
        $this->locked($order, function (Order $o) {
            if ($o->status !== Order::PENDING || ! $o->service?->allow_cancel_pending || $o->supplier_order_id) {
                throw new RuntimeException('This order can no longer be cancelled.');
            }

            $o->forceFill(['status' => Order::CANCELLED, 'completed_at' => now()])->save();
            $this->refund($o, "Refund · order #{$o->order_no} cancelled");
        });
    }

    /** Supplier could not take the order: hand it to a human (or reject if the service says so). */
    public function apiFailed(Order $order, string $error): void
    {
        $order->loadMissing('service');

        if ($order->service && ! $order->service->fallback_to_manual) {
            $this->reject($order, 'Service temporarily unavailable. Your credit has been refunded.', 'API error: '.$error);

            return;
        }

        $this->locked($order, function (Order $o) use ($error) {
            if (! $o->isOpen()) {
                return;
            }
            $o->forceFill([
                'processing_mode' => 'manual',
                'api_submitting_at' => null,
                'api_failed' => true,
                'api_error' => Str::limit($error, 1000),
                'api_next_check_at' => null,
            ])->save();

            Notify::admin('api_failed', '⚠️ <b>API order failed → manual queue</b>'
                ."\n#{$o->order_no} · ".Notify::e($o->service_name)
                ."\n".Notify::e(Str::limit($error, 300))
                ."\n".route('admin.orders.show', $o));
        });
    }

    /** Put an API-failed order back to the supplier. */
    public function retryApi(Order $order): void
    {
        $order->loadMissing('service');

        if (! $order->service?->usesApi()) {
            throw new RuntimeException('This service is not set to API mode.');
        }

        $this->locked($order, function (Order $o) {
            $this->assertOpen($o);
            $o->forceFill([
                'processing_mode' => 'api',
                'supplier_id' => $o->service->supplier_id,
                'api_failed' => false,
                'api_error' => null,
                'api_attempts' => 0,
                'api_submitting_at' => null,
                'status' => Order::PENDING,
            ])->save();
        });

        SubmitOrderToSupplier::dispatch($order->id)->afterCommit();
    }

    /** Tell the buyer; for bulk orders also send one summary when the last item finishes. */
    private function notifyFinished(Order $o): void
    {
        Notify::user($o->user, new OrderUpdated($o));

        if (! $o->batch_id) {
            return;
        }

        $open = Order::where('batch_id', $o->batch_id)->whereIn('status', [Order::PENDING, Order::IN_PROCESS])->exists();
        if ($open) {
            return;
        }

        $stats = Order::where('batch_id', $o->batch_id)->selectRaw(
            "SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS ok,"
            ." SUM(CASE WHEN status IN ('rejected', 'cancelled') THEN 1 ELSE 0 END) AS bad,"
            .' SUM(CASE WHEN refunded = 1 THEN price ELSE 0 END) AS refunded'
        )->first();

        Notify::user($o->user, new BatchFinished(
            $o->batch_id, $o->service_name, (int) $stats->ok, (int) $stats->bad, (float) $stats->refunded
        ));
    }

    private function refund(Order $o, string $description): void
    {
        if ($o->refunded) {
            return;
        }
        $this->wallet->credit($o->user, (float) $o->price, 'refund', $o, $description);
        $o->forceFill(['refunded' => true])->save();
    }

    private function assertOpen(Order $o): void
    {
        if (! $o->isOpen()) {
            throw new RuntimeException("Order #{$o->order_no} is already ".str_replace('_', ' ', $o->status).'.');
        }
    }

    /** Run $fn on a row-locked fresh copy, then refresh the caller's instance. */
    private function locked(Order $order, callable $fn): void
    {
        DB::transaction(function () use ($order, $fn) {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->with(['user', 'service'])->firstOrFail();
            $fn($fresh);
        });

        $order->refresh();
    }

    private function mainIdentifier(Service $service, array $input): ?string
    {
        foreach (['imei', 'sn', 'serial', 'ecid', 'username', 'email'] as $key) {
            if (filled($input[$key] ?? null)) {
                return Str::limit((string) $input[$key], 190, '');
            }
        }

        $first = collect($input)->first(fn ($v) => filled($v));

        return $first !== null ? Str::limit((string) $first, 190, '') : null;
    }
}
