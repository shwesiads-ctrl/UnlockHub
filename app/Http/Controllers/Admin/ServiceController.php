<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Supplier;
use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = Service::query()
            ->with(['category:id,name', 'supplier:id,name'])
            ->withCount('orders')
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->integer('category'), fn ($q, $c) => $q->where('service_category_id', $c))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('mode'), fn ($q, $m) => $q->where('processing_mode', $m))
            ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('type')->orderBy('service_category_id')->orderBy('sort')->orderBy('name')
            ->paginate(50)->withQueryString();

        return view('admin.services.index', [
            'services' => $services,
            'categories' => ServiceCategory::orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $service = new Service([
            'status' => 'hidden',
            'processing_mode' => 'manual',
            'fallback_to_manual' => true,
            'max_bulk' => 100,
            'service_category_id' => $request->integer('category') ?: null,
        ]);

        return view('admin.services.form', $this->formData($service));
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $service = DB::transaction(function () use ($request) {
            $service = new Service;
            $service->slug = $this->uniqueSlug($request->input('name'));
            $this->save($service, $request);

            return $service;
        });

        ActivityLog::record('service.created', $service);

        return redirect()->route('admin.services.edit', $service)->with('success', 'Service created.');
    }

    public function edit(Service $service): View
    {
        $service->load(['fields', 'prices']);

        return view('admin.services.form', $this->formData($service));
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        DB::transaction(fn () => $this->save($service, $request));

        ActivityLog::record('service.updated', $service);

        return back()->with('success', 'Service saved.');
    }

    public function toggle(Service $service): RedirectResponse
    {
        if ($service->status !== 'active' && (float) $service->default_price <= 0) {
            return back()->with('error', "Set a price for \"{$service->name}\" before making it Active.");
        }

        $service->update(['status' => $service->status === 'active' ? 'hidden' : 'active']);

        return back()->with('success', "\"{$service->name}\" is now ".ucfirst($service->status).'.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        if ($service->orders()->exists()) {
            $service->update(['status' => 'disabled']);

            return redirect()->route('admin.services.index')
                ->with('success', 'This service has orders, so it was Disabled instead of deleted.');
        }

        $service->delete();
        ActivityLog::record('service.deleted', null, ['name' => $service->name]);

        return redirect()->route('admin.services.index')->with('success', 'Service deleted.');
    }

    private function save(Service $service, ServiceRequest $request): void
    {
        $data = $request->validated();
        $category = ServiceCategory::findOrFail($data['service_category_id']);

        $service->fill([
            'service_category_id' => $category->id,
            'type' => $category->type,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'delivery_time' => $data['delivery_time'] ?? null,
            'cost_price' => $data['cost_price'],
            'default_price' => $data['default_price'],
            'points' => $data['points'] ?? null,
            'is_hot' => $data['is_hot'],
            'allow_bulk' => $data['allow_bulk'],
            'max_bulk' => $data['max_bulk'],
            'allow_cancel_pending' => $data['allow_cancel_pending'],
            'status' => $data['status'],
            'processing_mode' => $data['processing_mode'],
            'supplier_id' => $data['processing_mode'] === 'api' ? $data['supplier_id'] : null,
            'supplier_service_id' => $data['processing_mode'] === 'api' ? $data['supplier_service_id'] : null,
            'fallback_to_manual' => $data['fallback_to_manual'],
            'sort' => $data['sort'] ?? 0,
        ])->save();

        // Fields: replace the set (orders keep their own snapshot of input values)
        $service->fields()->delete();
        foreach ($data['fields'] ?? [] as $i => $f) {
            $service->fields()->create([
                'label' => $f['label'],
                'name' => $f['name'],
                'input_type' => $f['input_type'],
                'validation' => $f['validation'],
                'options' => $f['input_type'] === 'select'
                    ? array_values(array_filter(array_map('trim', explode(',', (string) $f['options']))))
                    : null,
                'regex' => $f['validation'] === 'regex' ? $f['regex'] : null,
                'placeholder' => $f['placeholder'],
                'is_required' => $f['is_required'],
                'is_bulk_field' => $f['is_bulk_field'] && $data['allow_bulk'],
                'sort' => $i,
            ]);
        }

        // Group prices: empty = use default price
        foreach (UserGroup::pluck('id') as $groupId) {
            $value = $data['prices'][$groupId] ?? null;

            if ($value === null || $value === '') {
                $service->prices()->where('user_group_id', $groupId)->delete();
            } else {
                $service->prices()->updateOrCreate(['user_group_id' => $groupId], ['price' => $value]);
            }
        }
    }

    private function formData(Service $service): array
    {
        return [
            'service' => $service,
            'categories' => ServiceCategory::orderBy('type')->orderBy('sort')->orderBy('name')->get()->groupBy('type'),
            'groups' => UserGroup::orderBy('sort')->get(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'status']),
            'groupPrices' => $service->exists ? $service->prices->pluck('price', 'user_group_id') : collect(),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $i = 1;
        while (Service::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }
}
