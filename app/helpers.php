<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /** Read a site setting from the settings table (cached). */
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            return Setting::get($key, $default);
        } catch (\Throwable) {
            return $default; // e.g. before migrations have run
        }
    }
}

if (! function_exists('money')) {
    /** Format an MMK amount: 125000 → "125,000 Ks". */
    function money(string|int|float|null $amount, string $currency = 'MMK'): string
    {
        $amount = (float) ($amount ?? 0);

        if ($currency === 'USD') {
            $rate = (float) setting('currency.usd_rate', 0);

            return $rate > 0 ? '$'.number_format($amount / $rate, 2) : '—';
        }

        return number_format($amount, 0).' Ks';
    }
}
