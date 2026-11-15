<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointTransaction extends Model
{
    protected $fillable = [
        'user_id', 'type', 'points', 'points_after', 'reference_type', 'reference_id', 'description',
    ];

    protected function casts(): array
    {
        return ['points' => 'integer', 'points_after' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
