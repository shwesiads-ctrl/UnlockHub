@extends('layouts.admin', ['title' => 'Delivery times', 'subtitle' => 'Real time from order placed → success'])

@php $h = fn ($m) => \App\Services\DeliveryStatsService::human((int) $m); @endphp

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <a href="{{ route('admin.reports.index') }}" class="btn h-9 text-[13px]">← Reports</a>
    @foreach ([7, 30] as $d)
        <a href="{{ route('admin.reports.delivery', ['days' => $d]) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => $days === $d])>Last {{ $d }} days</a>
    @endforeach
    <form method="POST" action="{{ route('admin.reports.delivery.refresh') }}" data-submit-once>@csrf<button class="btn h-9 text-[13px]">Recalculate now</button></form>
    <span class="ml-auto text-[13px] text-muted">Updated {{ $computedAt ? \Illuminate\Support\Carbon::parse($computedAt)->diffForHumans() : 'never' }} (hourly). Customers see an estimate only with ≥ {{ $minSamples }} orders.</span>
</div>

@if ($overdue->isNotEmpty())
<section class="card overflow-hidden">
    <h2 class="m-0 px-5 py-4 text-lg font-bold text-danger">Open orders past their alert time ({{ $overdue->count() }})</h2>
    <div class="table-wrap">
        <table class="table min-w-[640px]">
            <thead><tr><th>Order</th><th>Service</th><th>Status</th><th>Waiting</th><th>Alert after</th></tr></thead>
            <tbody>
            @foreach ($overdue as $o)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $o->order_no) }}" class="font-mono">#{{ $o->order_no }}</a></td>
                    <td>{{ \Illuminate\Support\Str::limit($o->service_name, 50) }}</td>
                    <td><x-status-badge :status="$o->status" /></td>
                    <td class="text-danger font-semibold">{{ $h($o->created_at->diffInMinutes(now(), true)) }}</td>
                    <td class="text-muted">{{ $h($o->max_delivery_minutes) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

<section class="card table-wrap">
    <table class="table min-w-[900px]">
        <thead><tr><th>Service / supplier</th><th class="text-right">Orders</th><th class="text-right">Median</th><th class="text-right">Most orders (25–75%)</th><th class="text-right">90% within</th><th class="text-right">Average</th><th>Advertised</th></tr></thead>
        <tbody>
        @forelse ($stats as $serviceId => $rows)
            @foreach ($rows as $r)
                <tr @class(['bg-surface-2/50' => $r->supplier_key > 0])>
                    <td>
                        @if ($r->supplier_key === 0)
                            <a href="{{ route('admin.services.edit', $r->service) }}" class="font-semibold">{{ \Illuminate\Support\Str::limit($r->service?->name, 60) }}</a>
                        @else
                            <span class="pl-4 text-[13px] text-muted">↳ {{ $r->supplier?->name ?? 'Supplier #'.$r->supplier_key }}</span>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($r->samples) }} @unless ($r->reliable()) <span class="badge bg-surface-2 text-muted" title="Too few orders for a reliable estimate">few</span> @endunless</td>
                    <td class="text-right font-semibold">{{ $h($r->median_minutes) }}</td>
                    <td class="text-right">{{ $h($r->p25_minutes) }} – {{ $h($r->p75_minutes) }}</td>
                    <td class="text-right">{{ $h($r->p90_minutes) }}</td>
                    <td class="text-right text-muted">{{ $h($r->avg_minutes) }}</td>
                    <td class="text-muted text-[13px]">@if ($r->supplier_key === 0) {{ $r->service?->delivery_time ?: '—' }} @endif</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="7" class="text-center text-muted py-10">No finished orders in this period yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
