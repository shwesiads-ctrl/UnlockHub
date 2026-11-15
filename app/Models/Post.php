<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'type', 'title', 'slug', 'excerpt', 'body', 'cover_path',
        'is_published', 'published_at', 'author_id',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->where('published_at', '<=', now());
    }
}
