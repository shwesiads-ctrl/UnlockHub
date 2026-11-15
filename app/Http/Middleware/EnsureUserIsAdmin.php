<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        // Admin › Settings › Security: staff must use Google Authenticator before opening the admin panel
        if (setting('security.staff_2fa') === '1' && ! $user->hasTwoFactor()) {
            return redirect()->route('account.security')
                ->with('error', 'Staff accounts must turn on Google Authenticator before using the admin panel.');
        }

        return $next($request);
    }
}
