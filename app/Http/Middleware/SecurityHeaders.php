<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser safety headers on every page:
 * - no framing by other sites (clickjacking on order / transfer buttons)
 * - no MIME sniffing (an uploaded file can't be run as a page)
 * - referrer only sends the site origin to other sites (no order numbers / tokens in URLs)
 * - HSTS on HTTPS in production
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Frame-Options', 'SAMEORIGIN', false);
        $h->set('X-Content-Type-Options', 'nosniff', false);
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)', false);
        if (! $h->has('Content-Security-Policy')) {
            $h->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self' https:");
        }

        if ($request->isSecure() && app()->isProduction()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        $h->remove('X-Powered-By');

        return $response;
    }
}
