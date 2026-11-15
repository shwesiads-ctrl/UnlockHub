<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StatementService;
use Illuminate\Console\Command;
use Throwable;

/** 1st of each month: email last month's PDF statement to users who had wallet activity. */
class SendMonthlyStatements extends Command
{
    protected $signature = 'statements:monthly {--month= : YYYY-MM to send (default: last month)} {--force : Send even if switched off in settings}';

    protected $description = "Email last month's wallet statement (PDF) to customers";

    public function handle(StatementService $statements): int
    {
        if (setting('statements.monthly', '1') !== '1' && ! $this->option('force')) {
            $this->info('Monthly statements are switched off in Admin › Settings.');

            return self::SUCCESS;
        }

        $month = $this->option('month')
            ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->option('month').'-01')
            : now()->subMonthNoOverflow();
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $sent = $failed = 0;

        User::query()
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('notify_email', true)
            ->whereHas('walletTransactions', fn ($q) => $q->whereBetween('created_at', [$from, $to]))
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($statements, $from, $to, &$sent, &$failed) {
                foreach ($users as $user) {
                    try {
                        $statements->email($user, $from, $to) ? $sent++ : null;
                    } catch (Throwable $e) {
                        report($e);
                        $failed++;
                        $this->error("{$user->username}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Statements for {$from->format('M Y')}: sent {$sent}, failed {$failed}.");

        return self::SUCCESS;
    }
}
