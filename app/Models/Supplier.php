<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'driver', 'api_url', 'api_username', 'api_key',
        'currency', 'exchange_rate', 'balance', 'status', 'last_error', 'last_synced_at',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'exchange_rate' => 'decimal:4',
            'balance' => 'decimal:4',
            'last_synced_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && filled($this->api_url) && filled($this->api_key);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
