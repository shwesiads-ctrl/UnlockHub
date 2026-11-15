<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceChangeRequest extends Model
{
    protected $fillable = ['service_id', 'old_prices', 'new_prices', 'old_cost', 'new_cost', 'change_percent', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['old_prices' => 'array', 'new_prices' => 'array', 'old_cost' => 'decimal:2', 'new_cost' => 'decimal:2', 'change_percent' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
