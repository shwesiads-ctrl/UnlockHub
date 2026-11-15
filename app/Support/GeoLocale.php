<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where is the visitor? Used to pick the first language and the payment
 * methods to show. It is only a starting guess — a language the visitor
 * picks, or a country saved on their profile, always wins.
 *
 * Sources, most reliable first (no outside API calls):
 *  1. Cloudflare's CF-IPCountry header (only trusted when the request really came from a Cloudflare edge IP)
 *  2. A GeoIP country set by the web server (cPanel/LiteSpeed/Apache mod_geoip: GEOIP_COUNTRY_CODE)
 *  3. The region in the browser language (Accept-Language: th-TH, vi-VN, zh-CN…)
 */
class GeoLocale
{
    /** Browser language → country, when the browser sends no region. */
    private const LANG_COUNTRY = ['my' => 'MM', 'th' => 'TH', 'lo' => 'LA', 'vi' => 'VN', 'zh' => 'CN'];

    public static function enabled(string $what): bool
    {
        try {
            return setting('geo.auto_'.$what, '1') === '1';
        } catch (\Throwable) {
            return false; // not installed yet
        }
    }

    /** Two-letter country code (upper case) or null. Cached in the session. */
    public static function country(Request $request): ?string
    {
        $session = $request->hasSession() ? $request->session() : null;
        if ($session && $session->has('geo_country')) {
            return $session->get('geo_country') ?: null;
        }

        $country = self::detect($request);
        $session?->put('geo_country', $country ?? '');

        return $country;
    }

    public static function detect(Request $request): ?string
    {
        if ($request->attributes->get('via_cloudflare') === true && ($cf = self::valid($request->header('CF-IPCountry')))) {
            return $cf;
        }

        if ($geo = self::valid($request->server('GEOIP_COUNTRY_CODE') ?? $request->server('MM_COUNTRY_CODE'))) {
            return $geo;
        }

        foreach (self::browserLanguages($request) as [$lang, $region]) {
            if ($region && ($c = self::valid($region))) {
                return $c;
            }
            if (isset(self::LANG_COUNTRY[$lang])) {
                return self::LANG_COUNTRY[$lang];
            }
        }

        return null;
    }

    /** Language for a visitor: from their country first, then their browser languages. */
    public static function locale(Request $request): ?string
    {
        $available = array_keys(config('unlockhub.locales', ['en' => 'English']));

        $country = self::country($request);
        $byCountry = config('unlockhub.country_locale', [])[$country] ?? null;
        if ($byCountry && in_array($byCountry, $available, true)) {
            return $byCountry;
        }

        // Not one of our countries (or unknown): use the first browser language we have.
        foreach (self::browserLanguages($request) as [$lang]) {
            if (in_array($lang, $available, true)) {
                return $lang;
            }
        }

        return $country ? 'en' : null;
    }

    /** Payment country for a visitor, only if we have methods grouped for it. */
    public static function paymentCountry(Request $request): ?string
    {
        $country = self::country($request);

        return $country && isset(config('unlockhub.countries', [])[$country]) ? $country : null;
    }

    /** @return list<array{0:string,1:?string}> [language, region] in the browser's order of preference */
    private static function browserLanguages(Request $request): array
    {
        $out = [];
        foreach (explode(',', (string) $request->header('Accept-Language')) as $i => $part) {
            [$tag, $q] = array_pad(explode(';q=', trim($part)), 2, '1');
            if (! preg_match('/^([a-z]{2,3})(?:-(?:Hans|Hant))?(?:-([A-Za-z]{2}))?/i', trim($tag), $m)) {
                continue;
            }
            $lang = strtolower($m[1]);
            $lang = $lang === 'bur' || $lang === 'mya' ? 'my' : $lang;
            $out[] = [(float) $q - $i / 1000, $lang, isset($m[2]) ? strtoupper($m[2]) : null];
        }
        usort($out, fn ($a, $b) => $b[0] <=> $a[0]);

        return array_map(fn ($r) => [$r[1], $r[2]], array_slice($out, 0, 8));
    }

    private static function valid(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        // XX = unknown, T1 = Tor, A1/A2 = proxies / satellite
        return preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, ['XX', 'T1', 'A1', 'A2', 'EU', 'AP'], true) ? $code : null;
    }
}
