<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Language: session choice → user's saved preference → app default. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('unlockhub.locales', ['en' => 'English']));

        $locale = $request->session()->get('locale')
            ?? $request->user()?->preferred_locale
            ?? config('app.locale');

        if (in_array($locale, $available, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
