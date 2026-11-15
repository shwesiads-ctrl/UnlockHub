<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WalletAdjustment;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Manual wallet changes by staff. Every change is recorded with who asked and who approved.
 * At or above the threshold (Admin › Staff) a second staff member must approve.
 */
class WalletAdjustmentService
{
    public function __construct(private WalletService $wallet) {}

    public static function threshold(): float
    {
        return max(0, (float) setting('wallet.approval_threshold', 0));
    }

    public function needsApproval(float $amount, string $kind = 'manual'): bool
    {
        return $kind === 'reconcile' || (self::threshold() > 0 && $amount >= self::threshold());
    }

    public function request(User $target, string $direction, float $amount, string $reason, User $by, string $kind = 'manual'): WalletAdjustment
    {
        if (! in_array($direction, ['credit', 'debit'], true) || $amount <= 0) {
            throw new RuntimeException('Invalid adjustment.');
        }

        $pending = $this->needsApproval($amount, $kind);

        return DB::transaction(function () use ($target, $direction, $amount, $reason, $by, $kind, $pending) {
            $adj = WalletAdjustment::create([
                'user_id' => $target->id, 'direction' => $direction, 'amount' => $amount, 'reason' => $reason,
                'kind' => $kind, 'status' => $pending ? 'pending' : 'approved', 'requested_by' => $by->id,
                'reviewed_by' => $pending ? null : $by->id, 'reviewed_at' => $pending ? null : now(),
            ]);

            if ($pending) {
                ActivityLog::record('wallet.adjustment_requested', $adj, ['user' => $target->username, 'direction' => $direction, 'amount' => $amount, 'reason' => $reason]);
                Notify::admin('payment', '🔐 <b>Wallet adjustment needs approval</b>'
                    ."\n".Notify::e($target->username).' · '.($direction === 'credit' ? '+' : '−').money($amount)
                    ."\nBy ".Notify::e($by->username).': '.Notify::e($reason)
                    ."\n".route('admin.wallet-approvals.index'));
            } else {
                $this->apply($adj);
                ActivityLog::record('user.wallet_'.$direction, $target, ['amount' => $amount, 'reason' => $reason, 'adjustment' => $adj->id]);
            }

            return $adj;
        });
    }

    public function approve(WalletAdjustment $adj, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($adj, $reviewer, $note) {
            $locked = WalletAdjustment::whereKey($adj->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new RuntimeException('Already '.$locked->status.'.');
            }
            if ($locked->requested_by === $reviewer->id) {
                throw new RuntimeException('A different staff member must approve this.');
            }

            $this->apply($locked);
            $locked->forceFill(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            ActivityLog::record('wallet.adjustment_approved', $locked, ['amount' => (float) $locked->amount, 'direction' => $locked->direction]);
        });
    }

    public function reject(WalletAdjustment $adj, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($adj, $reviewer, $note) {
            $locked = WalletAdjustment::whereKey($adj->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new RuntimeException('Already '.$locked->status.'.');
            }

            $locked->forceFill(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            ActivityLog::record('wallet.adjustment_rejected', $locked, ['note' => $note]);
        });
    }

    private function apply(WalletAdjustment $adj): void
    {
        $adj->loadMissing('user');
        $adj->direction === 'credit'
            ? $this->wallet->credit($adj->user, (float) $adj->amount, 'admin_credit', $adj, $adj->reason)
            : $this->wallet->debit($adj->user, (float) $adj->amount, 'admin_debit', $adj, $adj->reason);
    }
}
