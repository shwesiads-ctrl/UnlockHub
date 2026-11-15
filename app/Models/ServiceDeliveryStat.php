<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDeliveryStat extends Model
{
    public $timestamps = false;

    protected $fillable = ['service_id', 'supplier_key', 'window_days', 'samples', 'avg_minutes', 'median_minutes', 'p25_minutes', 'p75_minutes', 'p90_minutes', 'computed_at'];

    protected function casts(): array
    {
        return ['computed_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_key');
    }

    /** Enough finished orders to show as an estimate? */
    public function reliable(): bool
    {
        return $this->samples >= self::minSamples();
    }

    public static function minSamples(): int
    {
        return max(3, (int) setting('delivery.min_samples', 10));
    }
}
