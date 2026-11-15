<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\ReconciliationIssue;
use App\Models\ReconciliationRun;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Wallet reconciliation: compares balances with the transaction ledger and looks for
 * missing / duplicate refunds, debits and deposits.
 *
 * It only REPORTS. Corrections are proposed by one staff member and applied only after a
 * different staff member approves, each with a reason (ReconciliationIssue).
 */
class ReconciliationService
{
    private const EPS = 0.009;

    private const LIMIT = 500; // per check, so one bad day can't flood the table

    public function __construct(private WalletService $wallet) {}

    public function run(?User $by = null): ReconciliationRun
    {
        $run = ReconciliationRun::create(['triggered_by' => $by?->id, 'started_at' => now()]);

        $found = [];
        foreach ($this->checks() as $issue) {
            $found[$issue['fingerprint']] = $issue;
        }

        $new = 0;
        DB::transaction(function () use ($found, $run, &$new) {
            foreach ($found as $fp => $data) {
                $existing = ReconciliationIssue::where('fingerprint', $fp)->latest('id')->first();

                if ($existing && ($existing->isOpen() || $existing->status === 'ignored')) {
                    // Still there (or deliberately ignored): just refresh numbers
                    $existing->forceFill(['last_run_id' => $run->id, 'expected' => $data['expected'], 'actual' => $data['actual'], 'message' => $data['message']])->save();

                    continue;
                }

                ReconciliationIssue::create($data + ['first_run_id' => $run->id, 'last_run_id' => $run->id, 'status' => 'open']);
                $new++;
            }

            // Open issues not seen this time fixed themselves (or were fixed elsewhere)
            ReconciliationIssue::where('status', 'open')
                ->whereNotIn('fingerprint', array_keys($found) ?: ['-'])
                ->update(['status' => 'cleared', 'resolved_at' => now()]);
        });

        $run->forceFill([
            'users_checked' => User::count(),
            'transactions_checked' => WalletTransaction::count(),
            'issues_found' => count($found),
            'new_issues' => $new,
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    /** @return \Generator<array> */
    private function checks(): \Generator
    {
        // 1. users.balance vs SUM(transactions)
        $rows = DB::table('users')
            ->leftJoin('wallet_transactions as t', 't.user_id', '=', 'users.id')
            ->groupBy('users.id', 'users.balance')
            ->havingRaw('ABS(users.balance - COALESCE(SUM(t.amount), 0)) > ?', [self::EPS])
            ->limit(self::LIMIT)
            ->get(['users.id', 'users.balance', DB::raw('COALESCE(SUM(t.amount), 0) AS ledger')]);
        foreach ($rows as $r) {
            yield $this->issue('balance_mismatch', "user:{$r->id}", 'high', $r->id, null, (float) $r->ledger, (float) $r->balance,
                'Balance is '.money($r->balance).' but transactions add up to '.money($r->ledger).'.');
        }

        // 2. Running balance: previous balance_after + amount must equal balance_after
        $chain = DB::query()->fromSub(
            DB::table('wallet_transactions')->select('id', 'user_id', 'amount', 'balance_after')
                ->selectRaw('LAG(balance_after) OVER (PARTITION BY user_id ORDER BY id) AS prev'),
            'x'
        )->whereRaw('ABS(COALESCE(prev, 0) + amount - balance_after) > ?', [self::EPS])->limit(self::LIMIT)->get();
        foreach ($chain as $r) {
            yield $this->issue('chain_break', "tx:{$r->id}", 'medium', $r->user_id, ['wallet_transaction', $r->id],
                round((float) $r->prev + (float) $r->amount, 2), (float) $r->balance_after,
                "Transaction {$r->id}: expected balance ".money((float) $r->prev + (float) $r->amount).', recorded '.money($r->balance_after).'.');
        }

        $orderTx = fn (string $type) => DB::table('wallet_transactions')->where('type', $type)->where('reference_type', 'order');

        // 3. Rejected / cancelled orders without a refund
        $missing = Order::whereIn('status', [Order::REJECTED, Order::CANCELLED])
            ->whereNotIn('id', $orderTx('refund')->select('reference_id'))
            ->limit(self::LIMIT)->get(['id', 'order_no', 'user_id', 'price', 'status']);
        foreach ($missing as $o) {
            yield $this->issue('missing_refund', "order:{$o->id}:refund", 'high', $o->user_id, ['order', $o->id], (float) $o->price, 0,
                "Order #{$o->order_no} is {$o->status} but ".money($o->price).' was not refunded.');
        }

        // 4/5. Order charged or refunded more than once
        foreach (['refund' => 'duplicate_refund', 'order' => 'duplicate_debit'] as $type => $issue) {
            $dupes = $orderTx($type)->groupBy('reference_id', 'user_id')->havingRaw('COUNT(*) > 1')
                ->limit(self::LIMIT)->get(['reference_id', 'user_id', DB::raw('COUNT(*) AS n'), DB::raw('SUM(ABS(amount)) AS total')]);
            foreach ($dupes as $d) {
                yield $this->issue($issue, "order:{$d->reference_id}:{$issue}", 'high', $d->user_id, ['order', $d->reference_id],
                    round((float) $d->total / $d->n, 2), (float) $d->total,
                    'Order '.$d->reference_id.': '.$d->n.' '.($type === 'refund' ? 'refunds' : 'charges').' totalling '.money($d->total).'.');
            }
        }

        // 6. Refund on an order that succeeded
        $ok = Order::where('status', Order::SUCCESS)->whereIn('id', $orderTx('refund')->select('reference_id'))
            ->limit(self::LIMIT)->get(['id', 'order_no', 'user_id', 'price']);
        foreach ($ok as $o) {
            yield $this->issue('refund_on_success', "order:{$o->id}:refund_on_success", 'high', $o->user_id, ['order', $o->id], 0, (float) $o->price,
                "Order #{$o->order_no} succeeded but was also refunded.");
        }

        // 7. Order never charged
        $uncharged = Order::whereNotIn('id', $orderTx('order')->select('reference_id'))
            ->limit(self::LIMIT)->get(['id', 'order_no', 'user_id', 'price']);
        foreach ($uncharged as $o) {
            yield $this->issue('missing_debit', "order:{$o->id}:debit", 'high', $o->user_id, ['order', $o->id], (float) $o->price, 0,
                "Order #{$o->order_no} has no wallet charge of ".money($o->price).'.');
        }

        // 8. Approved top-ups: credited exactly once, with the approved amount
        $deposits = DB::table('wallet_transactions')->where('type', 'deposit')->where('reference_type', 'payment_request')
            ->groupBy('reference_id')->select('reference_id', DB::raw('COUNT(*) AS n'), DB::raw('SUM(amount) AS total'));
        $prs = PaymentRequest::where('status', PaymentRequest::APPROVED)
            ->leftJoinSub($deposits, 'd', 'd.reference_id', '=', 'payment_requests.id')
            ->where(fn ($q) => $q->whereNull('d.n')->orWhere('d.n', '>', 1)->orWhereRaw('ABS(d.total - payment_requests.credited_amount) > ?', [self::EPS]))
            ->limit(self::LIMIT)
            ->get(['payment_requests.id', 'payment_requests.user_id', 'payment_requests.credited_amount', 'payment_requests.transaction_id', 'd.n', 'd.total']);
        foreach ($prs as $p) {
            $type = $p->n === null ? 'missing_deposit' : ($p->n > 1 ? 'duplicate_deposit' : 'deposit_amount');
            yield $this->issue($type, "pr:{$p->id}:{$type}", 'high', $p->user_id, ['payment_request', $p->id], (float) $p->credited_amount, (float) ($p->total ?? 0),
                "Top-up {$p->transaction_id}: approved ".money($p->credited_amount).', credited '.money($p->total ?? 0).($p->n > 1 ? " in {$p->n} transactions" : '').'.');
        }

        // 9. Money-moving transactions that point at nothing
        $orphans = DB::table('wallet_transactions')->whereIn('type', ['order', 'refund', 'deposit'])->whereNull('reference_id')
            ->limit(self::LIMIT)->get(['id', 'user_id', 'type', 'amount']);
        foreach ($orphans as $t) {
            yield $this->issue('no_reference', "tx:{$t->id}:noref", 'low', $t->user_id, ['wallet_transaction', $t->id], null, (float) $t->amount,
                ucfirst($t->type)." transaction {$t->id} (".money($t->amount).') has no order / top-up reference.');
        }
    }

    private function issue(string $type, string $fp, string $severity, ?int $userId, ?array $subject, ?float $expected, ?float $actual, string $message): array
    {
        return [
            'fingerprint' => $type.'|'.$fp, 'type' => $type, 'severity' => $severity, 'user_id' => $userId,
            'subject_type' => $subject[0] ?? null, 'subject_id' => $subject[1] ?? null,
            'expected' => $expected, 'actual' => $actual, 'message' => mb_substr($message, 0, 255),
        ];
    }

    // ------------------------------------------------------------ corrections (two people)

    public function propose(ReconciliationIssue $issue, User $by, string $correction, ?float $amount, string $reason): void
    {
        if (! $issue->isOpen()) {
            throw new RuntimeException('This issue is already '.$issue->status.'.');
        }
        if (! array_key_exists($correction, ReconciliationIssue::CORRECTIONS)) {
            throw new RuntimeException('Unknown correction.');
        }
        if (! $issue->user_id) {
            throw new RuntimeException('This issue is not linked to a customer wallet.');
        }
        if ($correction !== 'sync_balance' && (! $amount || $amount <= 0)) {
            throw new RuntimeException('Enter the amount.');
        }

        $issue->forceFill([
            'status' => 'proposed', 'correction' => $correction,
            'correction_amount' => $correction === 'sync_balance' ? null : round($amount, 2),
            'reason' => $reason, 'proposed_by' => $by->id, 'approved_by' => null,
        ])->save();

        ActivityLog::record('reconcile.proposed', $issue, ['correction' => $correction, 'amount' => $amount, 'reason' => $reason]);
    }

    public function approve(ReconciliationIssue $issue, User $by): void
    {
        DB::transaction(function () use ($issue, $by) {
            $i = ReconciliationIssue::whereKey($issue->id)->lockForUpdate()->firstOrFail();

            if ($i->status !== 'proposed') {
                throw new RuntimeException('Nothing to approve.');
            }
            if ($i->proposed_by === $by->id) {
                throw new RuntimeException('A different staff member must approve this correction.');
            }

            $user = User::findOrFail($i->user_id);
            $note = "Reconciliation #{$i->id}: {$i->reason}";

            match ($i->correction) {
                'credit' => $this->wallet->credit($user, (float) $i->correction_amount, 'admin_credit', $i, $note),
                'debit' => $this->wallet->debit($user, (float) $i->correction_amount, 'admin_debit', $i, $note),
                'sync_balance' => $this->syncBalance($user, $i),
            };

            $i->forceFill(['status' => 'corrected', 'approved_by' => $by->id, 'resolved_at' => now()])->save();
            ActivityLog::record('reconcile.corrected', $i, ['correction' => $i->correction, 'amount' => (float) $i->correction_amount, 'reason' => $i->reason]);
        });
    }

    public function rejectProposal(ReconciliationIssue $issue, User $by): void
    {
        if ($issue->status !== 'proposed') {
            throw new RuntimeException('Nothing to reject.');
        }
        $issue->forceFill(['status' => 'open', 'correction' => null, 'correction_amount' => null, 'proposed_by' => null])->save();
        ActivityLog::record('reconcile.proposal_rejected', $issue);
    }

    public function ignore(ReconciliationIssue $issue, User $by, string $reason): void
    {
        if (! $issue->isOpen()) {
            throw new RuntimeException('This issue is already '.$issue->status.'.');
        }
        $issue->forceFill(['status' => 'ignored', 'reason' => $reason, 'approved_by' => $by->id, 'resolved_at' => now()])->save();
        ActivityLog::record('reconcile.ignored', $issue, ['reason' => $reason]);
    }

    private function syncBalance(User $user, ReconciliationIssue $i): void
    {
        $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        $ledger = round((float) WalletTransaction::where('user_id', $user->id)->sum('amount'), 2);
        $old = (float) $locked->balance;
        $locked->forceFill(['balance' => $ledger])->save();
        ActivityLog::record('reconcile.balance_synced', $locked, ['from' => $old, 'to' => $ledger, 'issue' => $i->id]);
    }

    /** Totals by transaction type for a period. */
    public function breakdown(?string $from = null, ?string $to = null): \Illuminate\Support\Collection
    {
        return WalletTransaction::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->groupBy('type')
            ->get(['type', DB::raw('COUNT(*) AS n'), DB::raw('SUM(amount) AS total')])
            ->keyBy('type');
    }
}
