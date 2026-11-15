<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseFaq extends Model
{
    protected $fillable = ['question', 'answer', 'sort', 'is_published'];

    protected function casts(): array
    {
        return ['sort' => 'integer', 'is_published' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
