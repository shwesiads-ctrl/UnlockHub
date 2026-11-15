<?php

namespace App\Console\Commands;

use App\Services\PricingService;
use App\Services\Suppliers\SupplierManager;
use Illuminate\Console\Command;

/** Hourly: fetch supplier price lists, log cost changes, reprice auto services, pause loss-makers. */
class SyncSupplierCosts extends Command
{
    protected $signature = 'suppliers:sync-costs';

    protected $description = 'Update service costs from supplier price lists';

    public function handle(PricingService $pricing, SupplierManager $manager): int
    {
        foreach ($pricing->syncAll($manager) as $name => $r) {
            isset($r['error'])
                ? $this->error("{$name}: {$r['error']}")
                : $this->line("{$name}: {$r['changed']} cost change(s), {$r['missing']} no longer offered");
        }

        return self::SUCCESS;
    }
}
