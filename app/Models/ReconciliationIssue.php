<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReconciliationIssue extends Model
{
    public const TYPES = [
        'balance_mismatch' => 'Balance ≠ sum of transactions',
        'chain_break' => 'Running balance broken',
        'missing_refund' => 'Rejected / cancelled order not refunded',
        'duplicate_refund' => 'Order refunded more than once',
        'refund_on_success' => 'Refund on a successful order',
        'duplicate_debit' => 'Order charged more than once',
        'missing_debit' => 'Order never charged',
        'missing_deposit' => 'Approved top-up not credited',
        'duplicate_deposit' => 'Top-up credited more than once',
        'deposit_amount' => 'Top-up credited with a different amount',
        'no_reference' => 'Transaction without reference',
    ];

    public const CORRECTIONS = [
        'credit' => 'Credit the wallet (adds a transaction)',
        'debit' => 'Debit the wallet (adds a transaction)',
        'sync_balance' => 'Set balance = sum of transactions (no new transaction)',
    ];

    protected $fillable = [
        'fingerprint', 'first_run_id', 'last_run_id', 'type', 'severity', 'user_id', 'subject_type', 'subject_id',
        'expected', 'actual', 'message', 'status', 'correction', 'correction_amount', 'reason', 'proposed_by', 'approved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['expected' => 'decimal:2', 'actual' => 'decimal:2', 'correction_amount' => 'decimal:2', 'resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'proposed'], true);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
