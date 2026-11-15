<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Supplier;
use App\Services\Suppliers\SupplierException;
use App\Services\Suppliers\SupplierManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(private SupplierManager $manager) {}

    public function index(): View
    {
        $suppliers = Supplier::withCount([
            'services',
            'orders as open_orders_count' => fn ($q) => $q->whereIn('status', ['pending', 'in_process']),
        ])->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('admin.suppliers.form', ['supplier' => new Supplier(['driver' => 'dhru', 'currency' => 'USD', 'exchange_rate' => 4500, 'status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = new Supplier;
        $this->fillFromRequest($supplier, $request, true);
        $supplier->save();

        ActivityLog::record('supplier.created', $supplier);

        return $this->testAndRedirect($supplier, 'Supplier added.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $oldRate = (float) $supplier->exchange_rate;
        $this->fillFromRequest($supplier, $request, false);
        $rateChanged = abs($oldRate - (float) $supplier->exchange_rate) > 0.00001;
        if ($rateChanged) {
            $supplier->exchange_rate_updated_at = now();
        }
        $supplier->save();

        ActivityLog::record('supplier.updated', $supplier, $rateChanged ? ['exchange_rate' => ['from' => $oldRate, 'to' => (float) $supplier->exchange_rate]] : []);

        if ($rateChanged) {
            $n = app(\App\Services\PricingService::class)->rateChanged($supplier, $request->user());
            session()->flash('info', "Exchange rate changed: costs of {$n} services recalculated.");
        }

        return $this->testAndRedirect($supplier, 'Supplier saved.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->services()->exists() || $supplier->orders()->whereIn('status', ['pending', 'in_process'])->exists()) {
            $supplier->update(['status' => 'disabled']);

            return redirect()->route('admin.suppliers.index')->with('success', 'Supplier has services or open orders, so it was Disabled instead of deleted.');
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier deleted.');
    }

    /** Test connection + update balance. */
    public function test(Supplier $supplier): RedirectResponse
    {
        return $this->testAndRedirect($supplier, null);
    }

    /** Browse the supplier's services and import them. */
    public function services(Request $request, Supplier $supplier): View|RedirectResponse
    {
        try {
            $list = $this->serviceList($supplier, $request->boolean('refresh'));
        } catch (SupplierException $e) {
            return redirect()->route('admin.suppliers.index')->with('error', "{$supplier->name}: {$e->getMessage()}");
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $list = array_values(array_filter($list, fn ($s) => Str::contains(Str::lower($s['name'].' '.$s['group']), Str::lower($search))));
        }

        $imported = Service::where('supplier_id', $supplier->id)->get(['id', 'slug', 'name', 'supplier_service_id', 'cost_price', 'default_price', 'status'])->keyBy('supplier_service_id');
        $servicePage = \App\Support\ListPage::from(collect($list), 25);

        return view('admin.suppliers.services', [
            'supplier' => $supplier,
            'groups' => $servicePage->getCollection()->groupBy('group'),
            'servicePage' => $servicePage,
            'total' => count($list),
            'imported' => $imported,
            'categories' => ServiceCategory::orderBy('type')->orderBy('name')->get()->groupBy('type'),
            'search' => $search,
        ]);
    }

    public function import(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['string', 'max:100'],
            'category_mode' => ['required', Rule::in(['supplier_groups', 'existing'])],
            'service_category_id' => ['nullable', 'required_if:category_mode,existing', 'exists:service_categories,id'],
            'type' => ['required', Rule::in(['auto', 'imei', 'server', 'remote'])],
            'markup' => ['required', 'numeric', 'min:0', 'max:1000'],
            'round_to' => ['required', 'integer', Rule::in([1, 10, 50, 100, 500, 1000])],
            'activate' => ['boolean'],
            'update_existing_price' => ['boolean'],
        ], ['ids.required' => 'Tick at least one service to import.']);

        try {
            $list = collect($this->serviceList($supplier))->keyBy('id');
        } catch (SupplierException $e) {
            return back()->with('error', $e->getMessage());
        }

        $created = 0;
        $updated = 0;
        $rate = (float) $supplier->exchange_rate;
        $round = (int) $data['round_to'];
        $existingCategory = $data['category_mode'] === 'existing' ? ServiceCategory::find($data['service_category_id']) : null;

        DB::transaction(function () use ($data, $list, $supplier, $rate, $round, $existingCategory, &$created, &$updated) {
            foreach ($data['ids'] as $id) {
                $s = $list->get($id);
                if (! $s) {
                    continue;
                }

                $type = $data['type'] === 'auto' ? $s['type'] : $data['type'];
                $cost = $this->roundUp($s['credit'] * $rate, $round);
                $price = $this->roundUp($cost * (1 + $data['markup'] / 100), $round);

                $service = Service::where('supplier_id', $supplier->id)->where('supplier_service_id', $id)->first();

                if ($service) {
                    app(\App\Services\PricingService::class)->setCost($service, $cost, (float) $s['credit'], 'import', request()->user());
                    $service->refresh();
                    if (! empty($data['update_existing_price'])) {
                        $service->default_price = $price;
                    }
                    $service->save();
                    $updated++;

                    continue;
                }

                $category = $existingCategory ?? ServiceCategory::firstOrCreate(
                    ['type' => $type, 'name' => Str::limit($s['group'], 95, '')],
                    ['slug' => $this->uniqueCategorySlug($type.'-'.$s['group'])]
                );

                $service = Service::create([
                    'service_category_id' => $category->id,
                    'type' => $category->type,
                    'name' => Str::limit($s['name'], 250, ''),
                    'slug' => $this->uniqueServiceSlug($s['name']),
                    'description' => $s['info'],
                    'delivery_time' => $s['time'] ? Str::limit($s['time'], 60, '') : null,
                    'cost_price' => $cost,
                    'supplier_cost' => $s['credit'],
                    'cost_updated_at' => now(),
                    'default_price' => $price,
                    'processing_mode' => 'api',
                    'supplier_id' => $supplier->id,
                    'supplier_service_id' => $id,
                    'fallback_to_manual' => true,
                    'allow_bulk' => $category->type === 'imei',
                    'status' => ! empty($data['activate']) && $price > 0 ? 'active' : 'hidden',
                ]);

                $this->defaultFields($service, $s);
                $created++;
            }
        });

        ActivityLog::record('supplier.import', $supplier, ['created' => $created, 'updated' => $updated]);

        return back()->with('success', "Imported {$created} new services, updated cost on {$updated}. Check each service's order fields before going live.");
    }

    /** Update cost price of every linked service from the supplier list. */
    public function syncCosts(Supplier $supplier): RedirectResponse
    {
        try {
            $list = collect($this->serviceList($supplier, true))->keyBy('id');
        } catch (SupplierException $e) {
            return back()->with('error', $e->getMessage());
        }

        ['changed' => $changed, 'missing' => $missing] = app(\App\Services\PricingService::class)
            ->syncSupplierCosts($supplier, $list->values()->all(), request()->user());

        $msg = "Costs updated on {$changed} services.";
        if ($missing) {
            $msg .= " {$missing} linked services are no longer offered by the supplier — check them.";
        }

        return back()->with('success', $msg);
    }

    private function serviceList(Supplier $supplier, bool $refresh = false): array
    {
        $key = "supplier.{$supplier->id}.services";
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(15), fn () => $this->manager->driverFor($supplier)->serviceList($supplier));
    }

    private function defaultFields(Service $service, array $s): void
    {
        $requires = array_map('strtolower', $s['requires']);

        if ($service->type === 'imei') {
            $service->fields()->create([
                'label' => 'IMEI', 'name' => 'imei', 'input_type' => 'text', 'validation' => 'imei',
                'placeholder' => '15 digits', 'is_required' => true, 'is_bulk_field' => true, 'sort' => 0,
            ]);
        }

        $sort = 1;
        foreach (['sn' => ['Serial Number', 'serial'], 'network' => ['Network', 'none'], 'mobile' => ['Model', 'none'], 'pin' => ['PIN', 'none'], 'kbh' => ['KBH', 'none'], 'mep' => ['MEP', 'none'], 'prd' => ['PRD', 'none'], 'reference' => ['Reference', 'none']] as $key => [$label, $validation]) {
            if (in_array($key, $requires, true)) {
                $service->fields()->create([
                    'label' => $label,
                    'name' => $key === 'mobile' ? 'modelid' : ($key === 'network' ? 'networkid' : $key),
                    'input_type' => 'text', 'validation' => $validation,
                    'is_required' => true, 'is_bulk_field' => false, 'sort' => $sort++,
                ]);
            }
        }

        // Server / remote services: most need a username or email for the tool account
        if ($service->type !== 'imei' && $service->fields()->doesntExist()) {
            $service->fields()->create([
                'label' => 'Username / Email', 'name' => 'username', 'input_type' => 'text', 'validation' => 'none',
                'is_required' => true, 'is_bulk_field' => false, 'sort' => 0,
            ]);
        }
    }

    private function fillFromRequest(Supplier $supplier, Request $request, bool $creating): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', Rule::in(array_keys(SupplierManager::DRIVERS))],
            'api_url' => ['required', 'url:http,https', 'max:255'],
            'api_username' => ['required', 'string', 'max:255'],
            'api_key' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['required', 'numeric', 'min:0.0001'],
            'status' => ['required', Rule::in(['active', 'disabled'])],
        ]);

        $supplier->fill(collect($data)->except('api_key')->all());
        $supplier->currency = strtoupper($data['currency']);

        if (filled($data['api_key'] ?? null)) {
            $supplier->api_key = $data['api_key']; // stored encrypted
        }
    }

    private function testAndRedirect(Supplier $supplier, ?string $prefix): RedirectResponse
    {
        try {
            $info = $this->manager->syncBalance($supplier);
            $msg = trim(($prefix ? $prefix.' ' : '').'Connection OK — balance '.number_format($info['credit'], 2).' '.$supplier->currency.'.');

            return redirect()->route('admin.suppliers.index')->with('success', $msg);
        } catch (SupplierException $e) {
            return redirect()->route('admin.suppliers.index')
                ->with('error', trim(($prefix ? $prefix.' ' : '')."Connection failed: {$e->getMessage()}"));
        }
    }

    private function roundUp(float $value, int $to): float
    {
        return $to > 1 ? ceil($value / $to) * $to : round($value, 2);
    }

    private function uniqueServiceSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'service';
        $base = Str::limit($base, 180, '');
        $slug = $base;
        $i = 1;
        while (Service::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $base): string
    {
        $base = Str::limit(Str::slug($base) ?: 'group', 180, '');
        $slug = $base;
        $i = 1;
        while (ServiceCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }
}
