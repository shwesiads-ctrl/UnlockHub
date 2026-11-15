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

        if ($user && ! $user->resellerProfile && $user->isResellerGroup()) {
            app(\App\Services\ResellerService::class)->provisionFromResellerGroup($user);
            $user->load('resellerProfile');
        }

        if (! $user?->isReseller()) {
            return redirect()->route('account.reseller.apply');
        }

        return $next($request);
    }
}
