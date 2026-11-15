<?php

namespace App\Console\Commands;

use App\Models\ReconciliationIssue;
use App\Models\User;
use App\Notifications\ReconciliationAlert;
use App\Services\ReconciliationService;
use App\Support\Notify;
use App\Support\Permissions;
use Illuminate\Console\Command;

/** Daily wallet check. Reports differences to Finance / Owner; never changes a balance. */
class ReconcileWallets extends Command
{
    protected $signature = 'wallet:reconcile';

    protected $description = 'Compare wallet balances with the transaction ledger and look for missing / duplicate money movements';

    public function handle(ReconciliationService $service): int
    {
        $run = $service->run();
        $open = ReconciliationIssue::whereIn('status', ['open', 'proposed'])->count();

        $this->info("Checked {$run->users_checked} users / {$run->transactions_checked} transactions: {$run->issues_found} issue(s), {$run->new_issues} new.");

        if ($run->new_issues > 0) {
            Notify::admin('reconcile', "⚖️ <b>Wallet reconciliation</b>\n{$run->new_issues} new issue(s), {$open} open.\n".route('admin.reconcile.index'));

            User::where('role', User::ROLE_ADMIN)->get()
                ->filter(fn ($u) => Permissions::allows($u, 'reconcile.view'))
                ->each(fn ($u) => Notify::user($u, new ReconciliationAlert($run->new_issues, $open)));
        }

        return self::SUCCESS;
    }
}
