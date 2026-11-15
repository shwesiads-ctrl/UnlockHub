<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    public const TYPES = [
        'imei' => 'IMEI / Serial',
        'server' => 'Server · Box · Tool',
        'remote' => 'Remote · Rent · Files',
    ];

    protected $fillable = [
        'service_category_id', 'type', 'name', 'slug', 'description', 'delivery_time',
        'cost_price', 'default_price', 'points', 'is_hot', 'allow_bulk', 'max_bulk',
        'allow_cancel_pending', 'duplicate_policy', 'status', 'processing_mode', 'supplier_id',
        'supplier_service_id', 'fallback_to_manual', 'sort', 'pricing_mode', 'price_currency', 'supplier_cost', 'cost_updated_at', 'sales_paused', 'paused_reason', 'max_delivery_minutes',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'default_price' => 'decimal:2',
            'points' => 'integer',
            'is_hot' => 'boolean',
            'allow_bulk' => 'boolean',
            'allow_cancel_pending' => 'boolean',
            'fallback_to_manual' => 'boolean',
            'popup' => 'array',
            'supplier_cost' => 'decimal:4',
            'cost_updated_at' => 'datetime',
            'sales_paused' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ServiceField::class)->orderBy('sort');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /** Price for a user (by group), falling back to default price. A reseller's customer pays the reseller's price. */
    public function priceFor(?User $user): string
    {
        if ($user?->parent_id && ($reseller = $user->activeReseller())) {
            return (string) \App\Services\ResellerService::sellPrice($this, $reseller);
        }

        if ($user?->user_group_id) {
            $price = $this->relationLoaded('prices')
                ? $this->prices->firstWhere('user_group_id', $user->user_group_id)?->price
                : $this->prices()->where('user_group_id', $user->user_group_id)->value('price');

            if ($price !== null) {
                return (string) $price;
            }
        }

        return (string) $this->default_price;
    }

    public function formattedPriceFor(?User $user): string
    {
        return unit_price($this->priceFor($user), $this->price_currency ?? 'ks');
    }

    /** Points earned for one successful order of this service. */
    public function pointsFor(string $price): int
    {
        if (($this->price_currency ?? 'ks') === 'points') {
            return 0;
        }

        if (! \App\Services\PointService::on('order')) {
            return 0;
        }

        if ($this->points !== null) {
            return $this->points;
        }

        $perKs = \App\Services\PointService::rules()['per_ks']; // 1 point per X Ks

        return $perKs > 0 ? intdiv((int) $price, $perKs) : 0;
    }

    public function usesApi(): bool
    {
        return $this->processing_mode === 'api' && $this->supplier_id && $this->supplier_service_id;
    }
}
