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

if (! function_exists('price')) {
    /** Format an MMK amount in the signed-in user's preferred currency. */
    function price(string|int|float|null $amount): string
    {
        return money($amount, auth()->user()?->preferred_currency ?? 'MMK');
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

if (! function_exists('pay_amount')) {
    /**
     * An amount in a payment method's own currency, e.g. "500 THB", "120.50 CNY".
     * MMK stays in the usual "125,000 Ks" form.
     */
    function pay_amount(string|int|float|null $amount, ?string $currency = 'MMK'): string
    {
        $currency = strtoupper($currency ?: 'MMK');
        if ($currency === 'MMK') {
            return money($amount);
        }
        $amount = (float) ($amount ?? 0);
        $decimals = in_array($currency, ['LAK', 'VND', 'KRW', 'JPY', 'IDR'], true) || floor($amount) == $amount ? 0 : 2;

        return number_format($amount, $decimals).' '.$currency;
    }
}
