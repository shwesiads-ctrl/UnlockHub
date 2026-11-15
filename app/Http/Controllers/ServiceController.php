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

        $servicePage = Service::visible()
            ->whereHas('category', fn ($q) => $q->active()->where('type', $type)
                ->when($categoryId, fn ($q) => $q->whereKey($categoryId)))
            ->when($hotOnly, fn ($q) => $q->where('is_hot', true))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->with([
                'category',
                'prices' => fn ($q) => $q->where('user_group_id', $groupId ?? 0),
                'fields:id,service_id,label,sort',
            ])
            ->orderBy(ServiceCategory::select('sort')->whereColumn('service_categories.id', 'services.service_category_id'))
            ->orderBy(ServiceCategory::select('name')->whereColumn('service_categories.id', 'services.service_category_id'))
            ->orderBy('service_category_id')->orderBy('sort')->orderBy('name')->orderBy('id')
            ->paginate(25)->withQueryString();
        $categories = $servicePage->getCollection()->groupBy('service_category_id')->map(function ($services) {
            return (clone $services->first()->category)->setRelation('services', $services);
        });

        // A search with no hits in this tab: tell the user which tabs have matches
        $otherTabHits = [];
        if ($search !== '' && $servicePage->total() === 0) {
            $otherTabHits = Service::visible()->where('name', 'like', '%'.$search.'%')
                ->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type')->all();
        }

        $favoriteIds = $request->user()?->favorites()->pluck('services.id')->all() ?? [];

        return view('services.index', compact('type', 'hotOnly', 'search', 'categoryId', 'allCategories', 'categories', 'otherTabHits', 'servicePage', 'favoriteIds'));
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
            'delivery' => \App\Models\ServiceDeliveryStat::where('service_id', $service->id)->where('supplier_key', 0)->where('window_days', 30)->first(),
            'isFavorite' => (bool) $request->user()?->favorites()->whereKey($service->id)->exists(),
        ]);
    }
}
