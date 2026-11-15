<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks admin URLs the staff member has no permission for (menu hiding alone isn't enough). */
class CheckStaffPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();

        if (! Permissions::allowsRoute($request->user(), $name)) {
            if ($name === 'admin.dashboard') {
                // No dashboard permission: send them to the first page they can open
                foreach (['admin.tickets.index', 'admin.orders.index', 'admin.payments.index', 'admin.posts.index'] as $r) {
                    if (\Illuminate\Support\Facades\Route::has($r) && Permissions::allowsRoute($request->user(), $r)) {
                        return redirect()->route($r);
                    }
                }
            }
            abort(403, 'Your staff role does not allow this.');
        }

        return $next($request);
    }
}
