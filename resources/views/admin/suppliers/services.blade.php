@extends('layouts.admin', ['title' => $supplier->name.' · Services', 'subtitle' => $total.' services offered · 1 '.$supplier->currency.' = '.number_format((float) $supplier->exchange_rate, 2).' Ks'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <a href="{{ route('admin.suppliers.index') }}" class="text-sm">← Suppliers</a>
    <form method="GET" class="ml-auto flex gap-2">
        <label for="q" class="sr-only">Search</label>
        <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Search supplier services…" class="input h-10 w-64">
        <button class="btn h-10">Search</button>
    </form>
    <a href="{{ route('admin.suppliers.services', [$supplier, 'refresh' => 1]) }}" class="btn h-10">Reload from supplier</a>
</div>

<form method="POST" action="{{ route('admin.suppliers.import', $supplier) }}" class="flex flex-wrap gap-5 items-start" data-import-form>
    @csrf

    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-4">
        @forelse ($groups as $group => $items)
            <section class="card overflow-hidden">
                <div class="flex items-center gap-3 px-5 py-3.5 bg-surface-2">
                    <label class="flex items-center gap-2.5 font-bold text-[15px] flex-1 cursor-pointer">
                        <input type="checkbox" class="w-5 h-5 accent-[var(--uh-brand)]" data-check-group="{{ md5($group) }}">
                        {{ $group }}
                    </label>
                    <span class="text-xs text-muted">{{ count($items) }}</span>
                </div>
                <div class="table-wrap">
                    <table class="table min-w-[720px]">
                        <thead><tr><th class="w-10"><span class="sr-only">Select</span></th><th>Service</th><th>Time</th><th class="text-right">Supplier price</th><th class="text-right">Your cost</th><th>Status here</th></tr></thead>
                        <tbody>
                        @foreach ($items as $s)
                            @php $mine = $imported[$s['id']] ?? null; @endphp
                            <tr>
                                <td>
                                    <label class="sr-only" for="s{{ $s['id'] }}">Select {{ $s['name'] }}</label>
                                    <input id="s{{ $s['id'] }}" type="checkbox" name="ids[]" value="{{ $s['id'] }}" class="w-5 h-5 accent-[var(--uh-brand)]" data-group="{{ md5($group) }}">
                                </td>
                                <td>
                                    <div class="font-medium">{{ $s['name'] }}</div>
                                    <div class="text-xs text-muted">ID {{ $s['id'] }} · {{ strtoupper($s['type']) }}@if ($s['requires']) · needs {{ implode(', ', $s['requires']) }}@endif</div>
                                </td>
                                <td class="text-muted text-[13px]">{{ $s['time'] ?: '—' }}</td>
                                <td class="text-right whitespace-nowrap">{{ number_format($s['credit'], 2) }} {{ $supplier->currency }}</td>
                                <td class="text-right whitespace-nowrap font-semibold">{{ money($s['credit'] * (float) $supplier->exchange_rate) }}</td>
                                <td>
                                    @if ($mine)
                                        <a href="{{ route('admin.services.edit', $mine->slug) }}" class="badge badge-success no-underline">Imported · {{ $mine->status }}</a>
                                        @if ((float) $mine->cost_price !== round($s['credit'] * (float) $supplier->exchange_rate, 2))
                                            <div class="text-[11px] text-warn mt-1">cost changed</div>
                                        @endif
                                    @else
                                        <span class="text-xs text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <div class="card p-10 text-center text-muted">No services{{ $search ? ' match "'.$search.'"' : '' }}.</div>
        @endforelse
        <p class="text-xs text-muted">Select and import services on this page before moving to the next page.</p>
        {{ $servicePage->links() }}
    </div>

    <aside class="flex-[1_1_320px] min-w-0 card p-5 flex flex-col gap-4 lg:sticky lg:top-4">
        <h2 class="m-0 text-lg font-bold">Import selected</h2>
        <x-field-error name="ids" />
        <fieldset class="m-0 p-0 border-0 flex flex-col gap-2">
            <legend class="label">Put them in</legend>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="category_mode" value="supplier_groups" class="accent-[var(--uh-brand)]" @checked(old('category_mode', 'supplier_groups') === 'supplier_groups')>Groups named like the supplier's</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="category_mode" value="existing" class="accent-[var(--uh-brand)]" @checked(old('category_mode') === 'existing')>One of my groups:</label>
            <label for="service_category_id" class="sr-only">Group</label>
            <select id="service_category_id" name="service_category_id" class="input h-10">
                <option value="">Select…</option>
                @foreach ($categories as $type => $cats)
                    <optgroup label="{{ \App\Models\Service::TYPES[$type] }}">
                        @foreach ($cats as $c) <option value="{{ $c->id }}" @selected((int) old('service_category_id') === $c->id)>{{ $c->name }}</option> @endforeach
                    </optgroup>
                @endforeach
            </select>
            <x-field-error name="service_category_id" />
        </fieldset>
        <div>
            <label for="type" class="label">Service type</label>
            <select id="type" name="type" class="input h-10">
                <option value="auto">Auto (from supplier)</option>
                @foreach (\App\Models\Service::TYPES as $k => $l) <option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option> @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="markup" class="label">Profit %</label>
                <input id="markup" name="markup" type="number" min="0" step="0.1" value="{{ old('markup', 15) }}" class="input h-10">
            </div>
            <div>
                <label for="round_to" class="label">Round up to</label>
                <select id="round_to" name="round_to" class="input h-10">
                    @foreach ([1, 10, 50, 100, 500, 1000] as $r) <option value="{{ $r }}" @selected((int) old('round_to', 100) === $r)>{{ $r === 1 ? 'No rounding' : number_format($r).' Ks' }}</option> @endforeach
                </select>
            </div>
        </div>
        <label class="flex items-start gap-2.5 text-sm"><input type="checkbox" name="activate" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]">Make new services Active right away</label>
        <label class="flex items-start gap-2.5 text-sm"><input type="checkbox" name="update_existing_price" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]">Also reset selling price of services already imported</label>
        <p class="m-0 text-xs text-muted leading-normal">New services use API mode with "Fallback to Manual" on. Group prices are left empty (default price applies) — set Reseller/VIP prices in each service.</p>
        <button class="btn btn-primary btn-lg"><span data-selected-count>0</span>&nbsp;selected · Import</button>
    </aside>
</form>
@endsection
