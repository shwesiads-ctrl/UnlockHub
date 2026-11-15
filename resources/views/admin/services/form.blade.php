@extends('layouts.admin', [
    'title' => $service->exists ? $service->name : 'New service',
    'subtitle' => $service->exists ? 'Services / Edit' : 'Services / Create',
])

@php
    $fieldRows = old('fields', $service->exists
        ? $service->fields->map(fn ($f) => [
            'label' => $f->label, 'name' => $f->name, 'input_type' => $f->input_type,
            'validation' => $f->validation, 'options' => implode(', ', $f->options ?? []),
            'regex' => $f->regex, 'placeholder' => $f->placeholder,
            'is_required' => $f->is_required, 'is_bulk_field' => $f->is_bulk_field,
        ])->all()
        : [['label' => 'IMEI', 'name' => 'imei', 'input_type' => 'text', 'validation' => 'imei', 'options' => '', 'regex' => '', 'placeholder' => '15 digits', 'is_required' => true, 'is_bulk_field' => true]]);
    $mode = old('processing_mode', $service->processing_mode ?? 'manual');
@endphp

@section('content')
@if ($service->sales_paused)
    <div class="badge-rejected rounded-xl px-4 py-3 text-sm font-medium" role="alert">
        ⛔ New orders are paused: {{ \Illuminate\Support\Str::after($service->paused_reason, ':') }}. Raise the price (or wait for the cost to drop) — it reopens automatically.
    </div>
@endif
@if ($pendingPrice)
    <div class="badge-pending rounded-xl px-4 py-3 text-sm font-medium">A price change of {{ number_format((float) $pendingPrice->change_percent, 1) }}% is waiting for approval in <a href="{{ route('admin.pricing.index') }}">Profit guard</a>.</div>
@endif
@if ($errors->any())
    <div class="badge-rejected rounded-xl px-4 py-3 text-sm font-medium" role="alert">Please fix the highlighted fields ({{ $errors->count() }}).</div>
@endif

<form method="POST" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}" class="flex flex-wrap gap-5 items-start" novalidate>
    @csrf
    @if ($service->exists) @method('PUT') @endif

    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-5">
        {{-- Basic info --}}
        <section class="card p-6 flex flex-col gap-4">
            <h2 class="m-0 text-lg font-bold">Basic info</h2>
            <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
                <div class="col-span-full">
                    <label for="name" class="label">Service name</label>
                    <input id="name" name="name" value="{{ old('name', $service->name) }}" class="input" required>
                    <x-field-error name="name" />
                </div>
                <div>
                    <label for="service_category_id" class="label">Group</label>
                    <select id="service_category_id" name="service_category_id" class="input" required>
                        <option value="">Select…</option>
                        @foreach ($categories as $type => $cats)
                            <optgroup label="{{ \App\Models\Service::TYPES[$type] }}">
                                @foreach ($cats as $c)
                                    <option value="{{ $c->id }}" @selected((int) old('service_category_id', $service->service_category_id) === $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <x-field-error name="service_category_id" />
                    <a href="{{ route('admin.categories.index') }}" class="text-xs">+ Add a group</a>
                </div>
                <div>
                    <label for="delivery_time" class="label">Delivery time</label>
                    <input id="delivery_time" name="delivery_time" value="{{ old('delivery_time', $service->delivery_time) }}" class="input" placeholder="e.g. 1-7 Days">
                </div>
                <div>
                    <label for="max_delivery_hours" class="label">Alert if open longer than (hours)</label>
                    <input id="max_delivery_hours" name="max_delivery_hours" type="number" min="0" step="0.25" value="{{ old('max_delivery_hours', $service->max_delivery_minutes ? round($service->max_delivery_minutes / 60, 2) : '') }}" class="input" placeholder="empty = no alert">
                    <x-field-error name="max_delivery_hours" />
                </div>
                <div>
                    <label for="status" class="label">Status</label>
                    <select id="status" name="status" class="input">
                        @foreach (['active' => 'Active', 'hidden' => 'Hidden', 'disabled' => 'Disabled'] as $k => $l)
                            <option value="{{ $k }}" @selected(old('status', $service->status) === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sort" class="label">Sort order</label>
                    <input id="sort" name="sort" type="number" min="0" value="{{ old('sort', $service->sort ?? 0) }}" class="input">
                </div>
            </div>
            <div class="flex flex-wrap gap-5 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="is_hot" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('is_hot', $service->is_hot))>HOT product</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="allow_bulk" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('allow_bulk', $service->allow_bulk))>Allow bulk orders</label>
                <label class="flex items-center gap-2">Max per bulk <input name="max_bulk" type="number" min="1" max="500" value="{{ old('max_bulk', $service->max_bulk ?? 100) }}" class="input h-9 w-20"></label>
                <label class="flex items-center gap-2"><input type="checkbox" name="allow_cancel_pending" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('allow_cancel_pending', $service->allow_cancel_pending))>User can cancel while Pending</label>
            </div>
            <x-field-error name="allow_bulk" />
            <div class="max-w-md">
                <label for="duplicate_policy" class="label">Same IMEI/SN ordered again while the first order is still open</label>
                <select id="duplicate_policy" name="duplicate_policy" class="input">
                    @foreach (['warn' => 'Warn — user can confirm and order again', 'block' => 'Block — never allow (API too)', 'allow' => 'Allow — no check'] as $k => $l)
                        <option value="{{ $k }}" @selected(old('duplicate_policy', $service->duplicate_policy ?? 'warn') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
                <span class="text-xs text-muted">API orders are refused on Warn too (they can't confirm).</span>
            </div>
            <div>
                <label for="description" class="label">Description <span class="font-normal text-muted">(shown to users)</span></label>
                <textarea id="description" name="description" rows="4" class="input">{{ old('description', $service->description) }}</textarea>
            </div>
        </section>

        {{-- Order fields --}}
        <section class="card p-6 flex flex-col gap-4">
            <div class="flex justify-between items-center flex-wrap gap-2">
                <h2 class="m-0 text-lg font-bold">Order fields</h2>
                <button type="button" class="btn h-9 text-sm text-brand-ink" data-add-row="#field-rows" data-template="#field-row-template">+ Add field</button>
            </div>
            <p class="m-0 text-[13px] text-muted">What the user must enter. The key is sent to the supplier API (e.g. <code>imei</code>, <code>sn</code>, <code>username</code>).</p>
            <div id="field-rows" class="flex flex-col gap-3" data-next-index="{{ count($fieldRows) }}">
                @foreach ($fieldRows as $i => $f)
                    @include('admin.services._field-row', ['i' => $i, 'f' => $f])
                @endforeach
            </div>
            <template id="field-row-template">
                @include('admin.services._field-row', ['i' => '__INDEX__', 'f' => ['label' => '', 'name' => '', 'input_type' => 'text', 'validation' => 'none', 'options' => '', 'regex' => '', 'placeholder' => '', 'is_required' => true, 'is_bulk_field' => false]])
            </template>
        </section>

        {{-- Processing --}}
        <section class="card p-6 flex flex-col gap-4" data-mode-section>
            <h2 class="m-0 text-lg font-bold">Processing mode</h2>
            <div role="radiogroup" aria-label="Processing mode" class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
                <label class="cursor-pointer rounded-xl border p-4 flex flex-col gap-1.5 has-[:checked]:border-2 has-[:checked]:border-brand has-[:checked]:bg-brand-soft border-line bg-bg">
                    <span class="flex items-center gap-2 font-bold"><input type="radio" name="processing_mode" value="manual" class="accent-[var(--uh-brand)]" @checked($mode === 'manual')>Manual</span>
                    <span class="text-[13px] text-muted">Order goes to the Manual queue. Admin enters the result.</span>
                </label>
                <label class="cursor-pointer rounded-xl border p-4 flex flex-col gap-1.5 has-[:checked]:border-2 has-[:checked]:border-brand has-[:checked]:bg-brand-soft border-line bg-bg">
                    <span class="flex items-center gap-2 font-bold"><input type="radio" name="processing_mode" value="api" class="accent-[var(--uh-brand)]" @checked($mode === 'api')>API</span>
                    <span class="text-[13px] text-muted">Sent to a supplier automatically. Result syncs back.</span>
                </label>
            </div>
            <div data-api-only @class(['flex flex-col gap-4 p-4 rounded-xl bg-surface-2', 'hidden' => $mode !== 'api'])>
                @if ($suppliers->isEmpty())
                    <p class="m-0 text-sm text-warn">No suppliers yet. <a href="{{ route('admin.suppliers.create') }}">Add one in Suppliers &amp; API</a> — until then use Manual.</p>
                @endif
                <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
                    <div>
                        <label for="supplier_id" class="label">Supplier</label>
                        <select id="supplier_id" name="supplier_id" class="input">
                            <option value="">Select…</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected((int) old('supplier_id', $service->supplier_id) === $s->id)>{{ $s->name }}{{ $s->status !== 'active' ? ' ('.$s->status.')' : '' }}</option>
                            @endforeach
                        </select>
                        <x-field-error name="supplier_id" />
                    </div>
                    <div>
                        <label for="supplier_service_id" class="label">Supplier service ID</label>
                        <input id="supplier_service_id" name="supplier_service_id" value="{{ old('supplier_service_id', $service->supplier_service_id) }}" class="input" placeholder="e.g. 1184">
                        <x-field-error name="supplier_service_id" />
                    </div>
                </div>
                <label class="flex items-start gap-2.5 text-sm leading-normal">
                    <input type="checkbox" name="fallback_to_manual" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(old('fallback_to_manual', $service->fallback_to_manual ?? true))>
                    <span><strong>Fallback to Manual</strong> if the API is down, returns an error, or supplier balance is too low.</span>
                </label>
            </div>
        </section>
    </div>

    <section id="popup" class="card p-6 flex flex-col gap-4 order-last basis-full scroll-mt-4">
        <div>
            <h2 class="m-0 text-lg font-bold">Service popup</h2>
            <p class="m-0 text-[13px] text-muted">Rules, steps and notes shown in a popup on the service page. Saved with the service.</p>
        </div>
        @php
            $popupState = old('popup') ? \App\Support\Popup::sanitize(json_decode(old('popup'), true) ?: []) : $service->popup;
        @endphp
        @include('admin.popups._editor', ['popup' => $popupState, 'kind' => 'service', 'barTitle' => old('name', $service->name), 'copyFrom' => $popupSources])
    </section>

    <aside class="flex-[1_1_340px] min-w-0 flex flex-col gap-5">
        <section class="card p-5 flex flex-col gap-4" data-service-pricing data-price-currency="{{ $priceCurrency ?? setting('catalog.price_currency', 'ks') }}" data-point-rate="{{ max(1, (int) setting('points.convert_rate', 10)) }}">
            @php $priceCurrency = setting('catalog.price_currency', 'ks'); @endphp
            <h2 class="m-0 text-lg font-bold">Pricing ({{ $priceCurrency === 'points' ? 'Points' : 'Ks' }})</h2>
            @php $pmode = old('pricing_mode', $service->pricing_mode ?? 'manual'); @endphp
            <div role="radiogroup" aria-label="Pricing mode" class="grid grid-cols-2 gap-2 text-sm">
                <label class="cursor-pointer rounded-xl border p-3 flex flex-col gap-1 has-[:checked]:border-2 has-[:checked]:border-brand has-[:checked]:bg-brand-soft border-line">
                    <span class="flex items-center gap-2 font-bold"><input type="radio" name="pricing_mode" value="manual" class="accent-[var(--uh-brand)]" @checked($pmode === 'manual')>Manual</span>
                    <span class="text-xs text-muted">You set the prices below.</span>
                </label>
                <label class="cursor-pointer rounded-xl border p-3 flex flex-col gap-1 has-[:checked]:border-2 has-[:checked]:border-brand has-[:checked]:bg-brand-soft border-line">
                    <span class="flex items-center gap-2 font-bold"><input type="radio" name="pricing_mode" value="auto" class="accent-[var(--uh-brand)]" @checked($pmode === 'auto')>Auto</span>
                    <span class="text-xs text-muted">From <a href="{{ route('admin.groups.index') }}">group rules</a>; follows cost.</span>
                </label>
            </div>
            @if ($rulePrices)
                <div class="rounded-[10px] bg-surface-2 p-3 text-xs flex flex-col gap-1">
                    <span class="font-semibold">Rule prices for cost {{ money($service->cost_price) }}{{ $pmode === 'auto' ? ' (in use)' : ' (if you switch to Auto)' }}</span>
                    <span>Default: {{ unit_price($rulePrices['default'], $priceCurrency) }}</span>
                    @foreach ($groups as $g) @isset($rulePrices['groups'][$g->id]) <span>{{ $g->name }}: {{ unit_price($rulePrices['groups'][$g->id], $priceCurrency) }}</span> @endisset @endforeach
                </div>
            @endif
            @if ($service->supplier_cost !== null && $service->supplier)
                <p class="m-0 text-xs text-muted">Supplier price {{ rtrim(rtrim(number_format((float) $service->supplier_cost, 4), '0'), '.') }} {{ $service->supplier->currency }} × {{ (float) $service->supplier->exchange_rate }} (rate updated {{ $service->supplier->exchange_rate_updated_at?->diffForHumans() ?? 'never' }}) · cost updated {{ $service->cost_updated_at?->diffForHumans() ?? 'never' }}</p>
            @endif
            <div>
                <label for="cost_price" class="label">Cost price</label>
                <input id="cost_price" name="cost_price" type="number" min="0" step="1" value="{{ old('cost_price', (float) ($service->cost_price ?? 0)) }}" class="input" data-cost>
                <x-field-error name="cost_price" />
            </div>
            <div>
                <label for="default_price" class="label">Default selling price ({{ $priceCurrency === 'points' ? 'Points' : 'Ks' }})</label>
                <input id="default_price" name="default_price" type="number" min="0" step="1" value="{{ old('default_price', (float) ($service->default_price ?? 0)) }}" class="input" data-price>
                <x-field-error name="default_price" />
            </div>
            <div class="flex flex-col gap-2.5">
                <span class="text-sm font-semibold">Price by group ({{ $priceCurrency === 'points' ? 'Points' : 'Ks' }}) <span class="font-normal text-muted">(empty = default)</span></span>
                @foreach ($groups as $g)
                    <div class="grid grid-cols-[1fr_9rem] gap-2.5 items-center text-sm">
                        <label for="price_{{ $g->id }}">{{ $g->name }}</label>
                        <input id="price_{{ $g->id }}" name="prices[{{ $g->id }}]" type="number" min="0" step="1" value="{{ old('prices.'.$g->id, isset($groupPrices[$g->id]) ? (float) $groupPrices[$g->id] : '') }}" class="input h-10" data-group-price>
                    </div>
                    <x-field-error :name="'prices.'.$g->id" />
                @endforeach
            </div>
            <div class="flex gap-2 items-center p-3 rounded-[10px] border border-dashed border-line">
                <label for="profit" class="text-sm">Profit</label>
                <input id="profit" type="number" min="0" value="15" class="input h-9 w-20" data-profit>
                <span class="text-sm">%</span>
                <button type="button" class="btn h-9 text-xs ml-auto" data-autofill-prices>Fill prices from cost</button>
            </div>
        </section>

        @if ($costChanges->isNotEmpty())
        <section class="card p-5 flex flex-col gap-2 text-[13px]">
            <h2 class="m-0 text-lg font-bold">Cost history</h2>
            @foreach ($costChanges as $c)
                <div class="flex justify-between gap-2">
                    <span class="text-muted whitespace-nowrap">{{ $c->created_at->format('d M H:i') }}</span>
                    <span>{{ $c->old_cost !== null ? money($c->old_cost) : '—' }} → <strong>{{ money($c->new_cost) }}</strong></span>
                    <span class="text-muted">{{ $c->source }}{{ $c->changer ? ' · '.$c->changer->username : '' }}</span>
                </div>
            @endforeach
        </section>
        @endif

        <section class="card p-5 flex flex-col gap-3">
            @if ($priceCurrency === 'points')
                <h2 class="m-0 text-lg font-bold flex items-center gap-2"><x-icon name="star" class="text-point" />Points payment</h2>
                <p class="m-0 text-[13px] text-muted">Successful orders paid with points do not earn points again.</p>
            @else
                <h2 class="m-0 text-lg font-bold flex items-center gap-2"><x-icon name="star" class="text-point" />Points reward</h2>
                <div>
                    <label for="points" class="label">Points per successful order</label>
                    <input id="points" name="points" type="number" min="0" value="{{ old('points', $service->points) }}" class="input">
                    <x-field-error name="points" />
                </div>
                <span class="text-[13px] text-muted">Empty = global rule (1 point per {{ number_format((int) setting('points.per_ks', 1000)) }} Ks).</span>
            @endif
        </section>

        <div class="flex gap-2">
            <a href="{{ route('admin.services.index') }}" class="btn flex-1">Cancel</a>
            <button class="btn btn-primary flex-[2]">Save service</button>
        </div>
    </aside>
</form>

@if ($service->exists)
    <form method="POST" action="{{ route('admin.services.destroy', $service) }}" class="self-start" onsubmit="return confirm('Delete this service? Services with orders are disabled instead.')">
        @csrf @method('DELETE')
        <button class="btn btn-danger h-10">Delete service</button>
    </form>
@endif
@endsection
