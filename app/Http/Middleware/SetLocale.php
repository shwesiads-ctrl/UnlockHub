<?php

namespace App\Http\Middleware;

use App\Support\GeoLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language, first match wins:
 *  1. picked in the language menu this session
 *  2. picked and saved on the account
 *  3. the visitor's location / browser language (Admin › Settings › Security › Visitor location)
 *  4. the account's saved language, then the site default
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('unlockhub.locales', ['en' => 'English']));
        $user = $request->user();

        $locale = $request->session()->get('locale');

        if (! $locale && $user && ! $user->locale_auto) {
            $locale = $user->preferred_locale;
        }

        if (! $locale && GeoLocale::enabled('locale')) {
            $locale = GeoLocale::locale($request);
        }

        $locale ??= $user?->preferred_locale ?? config('app.locale');

        if (in_array($locale, $available, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
