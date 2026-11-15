<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Affiliate commission: when a customer you referred completes an order, you earn a % of what
 * the site earned on it (affiliate.percent, of the site's sale price or of its profit).
 * Earnings (commission + reseller profit) can be withdrawn; top-ups cannot.
 */
class AffiliateService
{
    public const EARNING_TYPES = ['commission', 'reseller_profit'];

    public function __construct(private WalletService $wallet) {}

    public static function enabled(): bool
    {
        return setting('affiliate.enabled', '0') === '1' && (float) setting('affiliate.percent', 0) > 0;
    }

    public function payCommission(Order $order): void
    {
        if (! self::enabled() || $order->commission_paid) {
            return;
        }

        $buyer = $order->relationLoaded('user') ? $order->user : User::find($order->user_id);
        // Resellers' own customers don't pay commission (the reseller already earns the profit)
        if (! $buyer?->referred_by || $order->reseller_id || $buyer->referred_by === $buyer->id) {
            return;
        }

        $months = (int) setting('affiliate.months', 0); // 0 = for ever
        if ($months > 0 && $buyer->created_at && $buyer->created_at->lt(now()->subMonths($months))) {
            return;
        }

        $referrer = User::find($buyer->referred_by);
        if (! $referrer || ! $referrer->isActive()) {
            return;
        }

        $siteSale = (float) ($order->reseller_price ?? $order->price);
        $base = setting('affiliate.basis', 'profit') === 'sale' ? $siteSale : max(0, $siteSale - (float) $order->cost);
        $amount = round($base * (float) setting('affiliate.percent', 0) / 100, 2);

        if ($amount >= 1) {
            $this->wallet->credit($referrer, $amount, 'commission', $order, "Commission · {$buyer->username} order #{$order->order_no}");
        }
        $order->forceFill(['commission_paid' => true])->save();
    }

    /** Earned money that can be withdrawn (commission + reseller profit − withdrawals). */
    public static function withdrawable(User $user): float
    {
        $earned = (float) WalletTransaction::where('user_id', $user->id)->whereIn('type', self::EARNING_TYPES)->sum('amount');
        $out = (float) WithdrawalRequest::where('user_id', $user->id)->whereIn('status', ['pending', 'paid'])->sum('amount');

        return max(0, min(round($earned - $out, 2), (float) $user->fresh()->balance));
    }

    public function requestWithdrawal(User $user, float $amount, string $method, string $account): WithdrawalRequest
    {
        $min = (float) setting('affiliate.withdraw_min', 10000);
        if ($amount < $min) {
            throw new RuntimeException('Minimum withdrawal is '.money($min).'.');
        }

        return DB::transaction(function () use ($user, $amount, $method, $account) {
            User::whereKey($user->id)->lockForUpdate()->first(); // one request at a time per user
            if ($amount > self::withdrawable($user)) {
                throw new RuntimeException('You can withdraw up to '.money(self::withdrawable($user)).' (earnings only).');
            }
            $w = WithdrawalRequest::create(['user_id' => $user->id, 'amount' => round($amount, 2), 'method' => $method, 'account' => $account]);
            $this->wallet->debit($user, $amount, 'withdrawal', $w, "Withdrawal request #{$w->id} ({$method})");
            Notify::admin('payment', "🏦 <b>Withdrawal request</b> #{$w->id}\n".Notify::e($user->username).' · '.money($amount).' · '.Notify::e($method)."\n".route('admin.withdrawals.index'));

            return $w;
        });
    }

    public function markPaid(WithdrawalRequest $w, User $by, ?string $reference): void
    {
        DB::transaction(function () use ($w, $by, $reference) {
            $x = WithdrawalRequest::whereKey($w->id)->lockForUpdate()->firstOrFail();
            if ($x->status !== 'pending') {
                throw new RuntimeException('Already '.$x->status.'.');
            }
            $x->forceFill(['status' => 'paid', 'payout_reference' => $reference, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
            ActivityLog::record('withdrawal.paid', $x, ['amount' => (float) $x->amount, 'reference' => $reference]);
        });
    }

    public function reject(WithdrawalRequest $w, User $by, string $reason): void
    {
        DB::transaction(function () use ($w, $by, $reason) {
            $x = WithdrawalRequest::whereKey($w->id)->lockForUpdate()->with('user')->firstOrFail();
            if ($x->status !== 'pending') {
                throw new RuntimeException('Already '.$x->status.'.');
            }
            $this->wallet->credit($x->user, (float) $x->amount, 'withdrawal_refund', $x, "Withdrawal #{$x->id} returned: {$reason}");
            $x->forceFill(['status' => 'rejected', 'admin_note' => $reason, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
            ActivityLog::record('withdrawal.rejected', $x, ['reason' => $reason]);
        });
    }
}
