<?php

namespace App\Console\Commands;

use App\Services\DeliveryStatsService;
use Illuminate\Console\Command;

class ComputeDeliveryStats extends Command
{
    protected $signature = 'services:delivery-stats {--overdue : Only check for overdue open orders}';

    protected $description = 'Recalculate real delivery times (7 / 30 days) and alert on overdue open orders';

    public function handle(DeliveryStatsService $stats): int
    {
        if (! $this->option('overdue')) {
            $this->info($stats->compute().' delivery stat rows written.');
        }
        $this->info($stats->overdue()->count().' newly overdue order(s).');

        return self::SUCCESS;
    }
}
