<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequest extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'payment_method_id', 'amount', 'currency', 'rate', 'transaction_id', 'receipt_path',
        'status', 'credited_amount', 'reject_reason', 'admin_note', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'credited_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isForeign(): bool
    {
        return ($this->currency ?: 'MMK') !== 'MMK';
    }

    /** Ks the wallet should get by default: amount × rate (rounded to whole Ks). */
    public function suggestedCredit(): float
    {
        return round((float) $this->amount * (float) ($this->rate ?: 1));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Another request (any status except rejected) already used this transaction ID. */
    public function isDuplicate(): bool
    {
        return static::where('payment_method_id', $this->payment_method_id)
            ->where('transaction_id', $this->transaction_id)
            ->where('id', '!=', $this->id)
            ->where('status', '!=', self::REJECTED)
            ->exists();
    }
}
