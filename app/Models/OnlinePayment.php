<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlinePayment extends Model
{
    public const GATEWAYS = ['stripe' => 'Card (Stripe)', 'paypal' => 'PayPal'];

    protected $fillable = ['user_id', 'gateway', 'reference', 'amount', 'fee', 'currency', 'rate', 'credit_ks', 'status', 'gateway_txn', 'error', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'fee' => 'decimal:2', 'rate' => 'decimal:4', 'credit_ks' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
