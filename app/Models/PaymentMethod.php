<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name', 'code', 'country', 'currency', 'rate', 'account_name', 'account_number', 'qr_path',
        'instructions', 'min_amount', 'max_amount', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort');
    }

    /** Methods for one country, plus the ones marked "all countries". */
    public function scopeForCountry(Builder $q, ?string $country): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('country')->when($country, fn ($w) => $w->orWhere('country', $country)));
    }

    public function isForeign(): bool
    {
        return ($this->currency ?: 'MMK') !== 'MMK';
    }

    /** Ks for an amount paid in this method's currency. */
    public function toKs(float $amount): float
    {
        return round($amount * (float) ($this->rate ?: 1));
    }

    /**
     * Country to show payment methods for: the user's saved country, a country
     * picked on the page, where the visitor is, the language they use, then Myanmar.
     */
    public static function countryFor(?User $user = null): string
    {
        $countries = config('unlockhub.countries', ['MM' => 'Myanmar']);
        $request = request();

        $pick = $user?->country
            ?? session('pay_country')
            ?? (\App\Support\GeoLocale::enabled('payment') ? \App\Support\GeoLocale::paymentCountry($request) : null)
            ?? (config('unlockhub.locale_country')[app()->getLocale()] ?? null);

        return isset($countries[$pick]) ? $pick : 'MM';
    }
}
