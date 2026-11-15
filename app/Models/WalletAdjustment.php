<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A manual wallet change by staff. Large ones wait for a second person (status pending). */
class WalletAdjustment extends Model
{
    protected $fillable = ['user_id', 'direction', 'amount', 'reason', 'kind', 'status', 'requested_by', 'reviewed_by', 'review_note', 'reviewed_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function signedAmount(): float
    {
        return $this->direction === 'credit' ? (float) $this->amount : -(float) $this->amount;
    }
}
