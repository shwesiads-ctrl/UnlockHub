<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

/** Resolved tickets close for good after the reopen window (Admin › Settings › tickets.reopen_days). */
class CloseResolvedTickets extends Command
{
    protected $signature = 'tickets:close-resolved';

    protected $description = 'Close resolved tickets older than the reopen window';

    public function handle(): int
    {
        $n = Ticket::where('status', 'resolved')
            ->where('resolved_at', '<', now()->subDays(Ticket::reopenDays()))
            ->update(['status' => 'closed', 'closed_at' => now()]);

        $this->info("Closed {$n} ticket(s).");

        return self::SUCCESS;
    }
}
