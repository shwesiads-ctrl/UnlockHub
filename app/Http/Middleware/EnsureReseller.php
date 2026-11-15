<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReseller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('resellerProfile');

        if (! $user?->isReseller()) {
            return redirect()->route('account.reseller.apply');
        }

        return $next($request);
    }
}
