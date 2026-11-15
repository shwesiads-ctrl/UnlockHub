<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        $services = $user->favorites()
            ->with(['category:id,name', 'prices' => fn ($p) => $p->where('user_group_id', $user->user_group_id ?? 0)])
            ->when($q !== '', fn ($s) => $s->where('services.name', 'like', "%{$q}%"))
            ->orderBy('services.name')
            ->get();

        return view('account.favorites', compact('services', 'q'));
    }

    /** Add / remove. Works as a normal form post or with fetch (JSON). */
    public function toggle(Request $request, Service $service): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $on = ! $user->favorites()->whereKey($service->id)->exists();

        $on ? $user->favorites()->syncWithoutDetaching([$service->id => ['created_at' => now()]])
            : $user->favorites()->detach($service->id);

        if ($request->expectsJson()) {
            return response()->json(['favorite' => $on]);
        }

        return back()->with('success', $on ? "\"{$service->name}\" added to favourites." : 'Removed from favourites.');
    }
}
