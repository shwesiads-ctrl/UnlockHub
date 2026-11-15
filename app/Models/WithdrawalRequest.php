<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    protected $fillable = ['user_id', 'amount', 'method', 'account', 'status', 'admin_note', 'payout_reference', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
