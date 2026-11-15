<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public "Services & Reseller Pricing" pages. */
class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $type = array_key_exists($request->query('type'), Service::TYPES) ? $request->query('type') : 'imei';
        $hotOnly = $request->boolean('hot');
        $search = trim((string) $request->query('q', ''));
        $categoryId = $request->integer('category') ?: null;
        $groupId = $request->user()?->user_group_id;

        $allCategories = ServiceCategory::active()->where('type', $type)->orderBy('sort')->orderBy('name')->get();

        $categories = ServiceCategory::active()
            ->where('type', $type)
            ->when($categoryId, fn ($q) => $q->whereKey($categoryId))
            ->orderBy('sort')->orderBy('name')
            ->with(['services' => function ($q) use ($hotOnly, $search, $groupId) {
                $q->visible()
                    ->when($hotOnly, fn ($s) => $s->where('is_hot', true))
                    ->when($search !== '', fn ($s) => $s->where('name', 'like', '%'.$search.'%'))
                    ->with([
                        'prices' => fn ($p) => $p->where('user_group_id', $groupId ?? 0),
                        'fields:id,service_id,label,sort',
                    ]);
            }])
            ->get()
            ->filter(fn ($c) => $c->services->isNotEmpty());

        // A search with no hits in this tab: tell the user which tabs have matches
        $otherTabHits = [];
        if ($search !== '' && $categories->isEmpty()) {
            $otherTabHits = Service::visible()->where('name', 'like', '%'.$search.'%')
                ->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type')->all();
        }

        return view('services.index', compact('type', 'hotOnly', 'search', 'categoryId', 'allCategories', 'categories', 'otherTabHits'));
    }

    public function show(Request $request, Service $service): View
    {
        abort_unless($service->status === 'active', 404);

        $service->load(['category', 'fields', 'prices' => fn ($p) => $p->where('user_group_id', $request->user()?->user_group_id ?? 0)]);

        $price = $service->priceFor($request->user());

        return view('services.show', [
            'service' => $service,
            'price' => $price,
            'points' => $service->pointsFor($price),
        ]);
    }
}
