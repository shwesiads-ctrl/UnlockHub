<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    protected $fillable = ['user_id', 'name', 'message', 'rating', 'is_approved'];

    protected function casts(): array
    {
        return ['is_approved' => 'boolean', 'rating' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
