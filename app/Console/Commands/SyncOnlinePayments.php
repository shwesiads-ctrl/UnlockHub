<?php

namespace App\Console\Commands;

use App\Models\OnlinePayment;
use App\Services\Payments\OnlinePaymentService;
use Illuminate\Console\Command;

/** Safety net when a webhook or the return page is missed. */
class SyncOnlinePayments extends Command
{
    protected $signature = 'payments:sync-online';

    protected $description = 'Re-check pending card / PayPal payments with the gateway';

    public function handle(OnlinePaymentService $payments): int
    {
        $n = 0;
        OnlinePayment::where('status', 'pending')->whereNotNull('reference')
            ->where('created_at', '<', now()->subMinutes(2))->where('created_at', '>', now()->subDays(2))
            ->orderBy('id')->limit(200)->get()
            ->each(function ($p) use ($payments, &$n) {
                if ($payments->sync($p)->status === 'paid') {
                    $n++;
                }
            });
        $this->info("{$n} payment(s) confirmed.");

        return self::SUCCESS;
    }
}
