<?php

namespace App\Http\Middleware;

use App\Models\ResellerProfile;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * White-label shops.
 * - name.example.com (subdomain) or shop-domain.com (custom domain) → that reseller's shop.
 * - A reseller's customer on the main domain also sees their reseller's branding.
 * Branding = shop name, logo, contacts, colour (settings overridden for this request only).
 */
class ResolveResellerShop
{
    public function handle(Request $request, Closure $next): Response
    {
        Setting::clearOverrides();

        try {
            $whitelabel = setting('reseller.whitelabel', '1') === '1';
        } catch (\Throwable) {
            return $next($request); // not installed yet
        }

        $profile = null;
        $byDomain = false;

        if ($whitelabel) {
            $host = strtolower($request->getHost());
            $base = strtolower((string) (setting('reseller.base_domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST)));

            if ($base && $host !== $base && $host !== 'www.'.$base) {
                $profile = str_ends_with($host, '.'.$base)
                    ? ResellerProfile::where('subdomain', substr($host, 0, -strlen('.'.$base)))->first()
                    : ResellerProfile::where('custom_domain', $host)->first();
                $byDomain = (bool) $profile;

                if (! $profile && str_ends_with($host, '.'.$base) && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
                    abort(404, 'Shop not found.');
                }
                if ($profile && ! $profile->isApproved()) {
                    abort(404, 'This shop is not available.');
                }
            }
        }

        $user = $request->user();

        // On a reseller's domain: staff pages and other people's accounts belong on the main site
        if ($byDomain) {
            if ($request->is('admin', 'admin/*', 'install.php')) {
                return redirect()->away(rtrim((string) config('app.url'), '/').'/'.$request->path());
            }
            if ($user && $user->id !== $profile->user_id && $user->parent_id !== $profile->user_id) {
                return redirect()->away(rtrim((string) config('app.url'), '/').'/'.ltrim($request->getRequestUri(), '/'));
            }
        }

        // Logged-in customer of a reseller: always their reseller's branding
        if (! $profile && $whitelabel && $user?->parent_id && ($reseller = $user->activeReseller())) {
            $profile = $reseller->resellerProfile;
        }

        if ($profile && $profile->isApproved()) {
            app()->instance('reseller.shop', $profile);
            app()->instance('reseller.shop_by_domain', $byDomain);
            // Only the reseller's details: anything they didn't fill in stays empty (never ours)
            Setting::override([
                'site.name' => $profile->shop_name,
                'site.logo' => $profile->logo_path,
                'site.logo_show_name' => '1',
                'site.tagline' => $profile->about ? \Illuminate\Support\Str::limit($profile->about, 120, '') : null,
                'contact.email' => $profile->contact_email,
                'contact.phone' => $profile->contact_phone,
                'contact.address' => null,
                'social.telegram' => $profile->contact_telegram ? 'https://t.me/'.ltrim($profile->contact_telegram, '@') : null,
                'social.facebook' => null, 'social.viber' => null, 'social.youtube' => null,
                'pwa.short_name' => \Illuminate\Support\Str::limit($profile->shop_name, 12, ''),
                'pwa.theme_color' => $profile->brand_color,
            ]);
            view()->share('shop', $profile);
        }

        return $next($request);
    }
}
