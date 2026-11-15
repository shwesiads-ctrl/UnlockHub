<?php

namespace App\Http\Middleware;

use App\Services\SessionSecurityService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSessionIsCurrent
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$request->hasSession() || !$user) {
            return $next($request);
        }
        $stamp = $request->session()->get(SessionSecurityService::KEY);
        // A valid remember cookie has just re-authenticated via the current rotated token.
        if ($stamp === null && Auth::guard()->viaRemember()) {
            SessionSecurityService::stamp($request, $user);
            $stamp = $request->session()->get(SessionSecurityService::KEY);
        }
        $version = (int) $user->session_version;
        // Existing sessions from before this change are valid only until the first reset.
        if (($stamp === null && $version !== 0) || ($stamp !== null && (
            !is_array($stamp) || ($stamp['user_id'] ?? null) !== $user->id || ($stamp['version'] ?? null) !== $version
        ))) {
            Auth::guard()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw new AuthenticationException('Your password changed. Please sign in again.', ['web'], route('login'));
        }
        SessionSecurityService::stamp($request, $user);

        return $next($request);
    }
}
