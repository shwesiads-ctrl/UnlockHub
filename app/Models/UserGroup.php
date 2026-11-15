<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGroup extends Model
{
    protected $fillable = ['name', 'slug', 'min_points_for_auto_upgrade', 'is_default', 'sort', 'markup_type', 'markup_value', 'min_profit', 'badge_color', 'badge_icon'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function defaultId(): ?int
    {
        return static::query()->where('is_default', true)->value('id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }
}
