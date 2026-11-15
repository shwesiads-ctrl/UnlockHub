<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\PaymentRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Approve / reject top-up requests. Approving credits the wallet exactly once. */
class PaymentApprovalService
{
    public function __construct(private WalletService $wallet) {}

    public function approve(PaymentRequest $request, float $creditAmount, ?string $note = null): PaymentRequest
    {
        if ($creditAmount <= 0) {
            throw new RuntimeException('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($request, $creditAmount, $note) {
            /** @var PaymentRequest $locked */
            $locked = PaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentRequest::PENDING) {
                throw new RuntimeException('This request was already '.$locked->status.'.');
            }

            $locked->forceFill([
                'status' => PaymentRequest::APPROVED,
                'credited_amount' => $creditAmount,
                'admin_note' => $note,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ])->save();

            $locked->loadMissing('user', 'method');

            $this->wallet->credit(
                $locked->user,
                $creditAmount,
                'deposit',
                $locked,
                "Top-up via {$locked->method->name} (Txn {$locked->transaction_id})"
            );

            ActivityLog::record('payment.approved', $locked, ['credited' => $creditAmount]);

            return $locked;
        });
    }

    public function reject(PaymentRequest $request, string $reason, ?string $note = null): PaymentRequest
    {
        return DB::transaction(function () use ($request, $reason, $note) {
            /** @var PaymentRequest $locked */
            $locked = PaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentRequest::PENDING) {
                throw new RuntimeException('This request was already '.$locked->status.'.');
            }

            $locked->forceFill([
                'status' => PaymentRequest::REJECTED,
                'reject_reason' => $reason,
                'admin_note' => $note,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ])->save();

            ActivityLog::record('payment.rejected', $locked, ['reason' => $reason]);

            return $locked;
        });
    }
}
