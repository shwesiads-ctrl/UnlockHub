<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCostChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['service_id', 'supplier_id', 'old_cost', 'new_cost', 'old_supplier_cost', 'new_supplier_cost', 'exchange_rate', 'source', 'changed_by'];

    protected function casts(): array
    {
        return ['old_cost' => 'decimal:2', 'new_cost' => 'decimal:2', 'old_supplier_cost' => 'decimal:4', 'new_supplier_cost' => 'decimal:4', 'exchange_rate' => 'decimal:4'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function changePercent(): ?float
    {
        return (float) $this->old_cost > 0 ? round(((float) $this->new_cost - (float) $this->old_cost) / (float) $this->old_cost * 100, 1) : null;
    }
}
