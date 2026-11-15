<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    protected $fillable = ['service_id', 'user_group_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }
}
