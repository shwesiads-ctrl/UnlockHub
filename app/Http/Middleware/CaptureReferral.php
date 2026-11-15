<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/** Any page visited with ?ref=CODE stores the code for 30 days. */
class CaptureReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        $ref = $request->query('ref');

        if (is_string($ref) && preg_match('/^[A-Za-z0-9]{4,16}$/', $ref) && ! $request->user()) {
            Cookie::queue('uh_ref', strtoupper($ref), 60 * 24 * 30);
        }

        return $next($request);
    }
}
