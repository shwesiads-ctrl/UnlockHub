<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Suppliers\SupplierException;
use App\Services\Suppliers\SupplierManager;
use Illuminate\Console\Command;

/** Refresh supplier balances (hourly). */
class SyncSuppliers extends Command
{
    protected $signature = 'suppliers:sync';

    protected $description = 'Refresh balance and connection status for every active supplier';

    public function handle(SupplierManager $manager): int
    {
        foreach (Supplier::whereIn('status', ['active', 'error'])->get() as $supplier) {
            try {
                $info = $manager->syncBalance($supplier);
                $this->line("{$supplier->name}: ".number_format($info['credit'], 2)." {$supplier->currency}");

                $limit = (float) setting('telegram.low_balance', 20);
                if ($limit > 0 && $info['credit'] < $limit) {
                    \App\Support\Notify::admin('low_balance', '🔻 <b>Low supplier balance</b>'
                        ."\n".\App\Support\Notify::e($supplier->name).': '.number_format($info['credit'], 2).' '.$supplier->currency
                        ."\nTop up to keep API orders running.");
                }
            } catch (SupplierException $e) {
                $this->error("{$supplier->name}: {$e->getMessage()}");
                \App\Support\Notify::admin('api_failed', '⚠️ <b>Supplier connection error</b>'
                    ."\n".\App\Support\Notify::e($supplier->name).': '.\App\Support\Notify::e($e->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
