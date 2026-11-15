@extends('layouts.admin', ['title' => 'Profit guard', 'subtitle' => 'Costs, prices and margins'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <form method="POST" action="{{ route('admin.pricing.sync') }}" data-submit-once>@csrf<button class="btn btn-primary">Check supplier costs now</button></form>
    <a href="{{ route('admin.groups.index') }}" class="btn">Pricing rules</a>
    <span class="text-[13px] text-muted ml-auto">Costs sync hourly. Placed orders always keep their own price and cost.</span>
</div>

@if ($requests->isNotEmpty())
<section class="card overflow-hidden">
    <h2 class="m-0 px-5 py-4 text-lg font-bold">Price changes waiting for approval</h2>
    <div class="table-wrap">
        <table class="table min-w-[760px]">
            <thead><tr><th>Service</th><th class="text-right">Cost</th><th class="text-right">Default price</th><th class="text-right">Change</th><th></th></tr></thead>
            <tbody>
            @foreach ($requests as $r)
                <tr>
                    <td><a href="{{ route('admin.services.edit', $r->service) }}">{{ \Illuminate\Support\Str::limit($r->service->name, 60) }}</a><div class="text-xs text-muted">{{ $r->created_at->diffForHumans() }}</div></td>
                    <td class="text-right whitespace-nowrap">{{ $r->old_cost !== null ? money($r->old_cost).' → ' : '' }}<strong>{{ money($r->new_cost) }}</strong></td>
                    <td class="text-right whitespace-nowrap">{{ money($r->old_prices['default'] ?? 0) }} → <strong>{{ money($r->new_prices['default'] ?? 0) }}</strong>
                        @if (! empty($r->new_prices['groups'])) <div class="text-xs text-muted">+ {{ count($r->new_prices['groups']) }} group price(s)</div> @endif</td>
                    <td @class(['text-right font-bold', 'text-danger' => ($r->new_prices['default'] ?? 0) > ($r->old_prices['default'] ?? 0), 'text-ok' => ($r->new_prices['default'] ?? 0) < ($r->old_prices['default'] ?? 0)])>{{ number_format((float) $r->change_percent, 1) }}%</td>
                    <td class="text-right whitespace-nowrap">
                        <form method="POST" action="{{ route('admin.pricing.approve', $r) }}" class="inline">@csrf<button class="btn btn-primary h-9 text-xs">Apply</button></form>
                        <form method="POST" action="{{ route('admin.pricing.reject', $r) }}" class="inline">@csrf<button class="btn h-9 text-xs">Keep old</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

<section class="card overflow-hidden">
    <div class="px-5 py-4">
        <h2 class="m-0 text-lg font-bold">Selling at a loss or thin margin</h2>
        <p class="m-0 text-[13px] text-muted">Lowest price (any group) minus cost {{ $minProfit > 0 ? 'below '.money($minProfit) : 'at or below zero' }}.</p>
    </div>
    <div class="table-wrap">
        <table class="table min-w-[760px]">
            <thead><tr><th>Service</th><th class="text-right">Cost</th><th class="text-right">Lowest price</th><th class="text-right">Profit</th><th>Pricing</th><th>Orders</th></tr></thead>
            <tbody>
            @forelse ($risky as $s)
                <tr>
                    <td><a href="{{ route('admin.services.edit', $s) }}">{{ \Illuminate\Support\Str::limit($s->name, 60) }}</a>@if ($s->status !== 'active') <span class="badge bg-surface-2 text-muted">{{ $s->status }}</span>@endif</td>
                    <td class="text-right">{{ money($s->cost_price) }}</td>
                    <td class="text-right">{{ money($s->lowest) }}</td>
                    <td @class(['text-right font-bold', 'text-danger' => $s->lowest <= $s->cost_price, 'text-warn' => $s->lowest > $s->cost_price])>{{ money($s->lowest - $s->cost_price) }}</td>
                    <td class="text-muted">{{ ucfirst($s->pricing_mode) }}</td>
                    <td>@if ($s->sales_paused) <span class="badge badge-rejected">Paused</span> @else <span class="badge badge-success">Open</span> @endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-8">Every service makes a profit. 👍</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@if ($paused->isNotEmpty())
<section class="card p-5 flex flex-col gap-2">
    <h2 class="m-0 text-lg font-bold">Orders paused</h2>
    @foreach ($paused as $p)
        <div class="text-sm"><a href="{{ route('admin.services.edit', $p) }}">{{ $p->name }}</a> — <span class="text-muted">{{ \Illuminate\Support\Str::after($p->paused_reason, ':') }}</span></div>
    @endforeach
    <p class="m-0 text-xs text-muted">They open again automatically once the price is above cost.</p>
</section>
@endif

<section class="card overflow-hidden">
    <h2 class="m-0 px-5 py-4 text-lg font-bold">Cost changes</h2>
    <div class="table-wrap">
        <table class="table min-w-[860px]">
            <thead><tr><th>When</th><th>Service</th><th class="text-right">Old cost</th><th class="text-right">New cost</th><th class="text-right">Change</th><th>Supplier price</th><th>Source</th></tr></thead>
            <tbody>
            @forelse ($changes as $c)
                <tr>
                    <td class="whitespace-nowrap text-muted">{{ $c->created_at->format('d M H:i') }}</td>
                    <td>@if ($c->service)<a href="{{ route('admin.services.edit', $c->service) }}">{{ \Illuminate\Support\Str::limit($c->service->name, 50) }}</a>@endif</td>
                    <td class="text-right">{{ $c->old_cost !== null ? money($c->old_cost) : '—' }}</td>
                    <td class="text-right font-semibold">{{ money($c->new_cost) }}</td>
                    <td @class(['text-right', 'text-danger' => $c->changePercent() > 0, 'text-ok' => $c->changePercent() < 0])>{{ $c->changePercent() !== null ? ($c->changePercent() > 0 ? '+' : '').$c->changePercent().'%' : '—' }}</td>
                    <td class="text-[13px] text-muted">@if ($c->new_supplier_cost !== null){{ rtrim(rtrim(number_format((float) $c->old_supplier_cost, 4), '0'), '.') }} → {{ rtrim(rtrim(number_format((float) $c->new_supplier_cost, 4), '0'), '.') }} @ {{ (float) $c->exchange_rate }}@endif</td>
                    <td class="text-muted">{{ $c->source }}{{ $c->changer ? ' · '.$c->changer->username : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-8">No cost changes yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
{{ $changes->links() }}
@endsection
