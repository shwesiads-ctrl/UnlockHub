@extends('layouts.admin', ['title' => 'Reports', 'subtitle' => $from->format('d M Y').' – '.$to->format('d M Y')])

@section('content')
<form method="GET" class="flex flex-wrap gap-2 items-end">
    <nav aria-label="Quick ranges" class="flex flex-wrap gap-2">
        @foreach (['today' => 'Today', '7d' => '7 days', 'month' => 'This month', 'last_month' => 'Last month'] as $k => $l)
            <a href="{{ route('admin.reports.index', ['preset' => $k]) }}" @class(['btn h-10', 'btn-primary' => request('preset') === $k])>{{ $l }}</a>
        @endforeach
    </nav>
    <div class="flex gap-2 items-end ml-auto">
        <div><label for="from" class="label text-xs">From</label><input id="from" name="from" type="date" value="{{ $from->toDateString() }}" class="input h-10"></div>
        <div><label for="to" class="label text-xs">To</label><input id="to" name="to" type="date" value="{{ $to->toDateString() }}" class="input h-10"></div>
        <button class="btn h-10">Show</button>
        <a href="{{ route('admin.reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn h-10">CSV</a>
        <a href="{{ route('admin.reports.delivery') }}" class="btn h-10">Delivery times</a>
    </div>
</form>

<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
    <div class="card p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold text-muted">Sales</span><span class="font-display text-[26px] font-bold">{{ money($totals['sales']) }}</span><span class="text-xs text-muted">completed orders</span></div>
    <div class="card p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold text-muted">Profit</span><span class="font-display text-[26px] font-bold text-ok">{{ money($totals['profit']) }}</span><span class="text-xs text-muted">cost {{ money($totals['cost']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold text-muted">Orders</span><span class="font-display text-[26px] font-bold">{{ number_format($totals['placed']) }}</span><span class="text-xs text-muted">{{ $totals['success'] }} ok · {{ $totals['rejected'] }} rejected @if ($totals['success_rate'] !== null) · {{ $totals['success_rate'] }}% @endif</span></div>
    <div class="card p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold text-muted">Top-ups approved</span><span class="font-display text-[26px] font-bold">{{ money($totals['deposits']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold text-muted">New users</span><span class="font-display text-[26px] font-bold">{{ number_format($totals['new_users']) }}</span></div>
</div>

@php $max = max(1, max(array_column($days, 'sales') ?: [0])); $showChart = count($days) <= 62; @endphp
@if ($showChart)
<section class="card p-5 flex flex-col gap-3">
    <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Daily sales &amp; profit</h2><span class="text-xs text-muted flex gap-3"><span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-brand-soft inline-block" aria-hidden="true"></span>Sales</span><span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-brand inline-block" aria-hidden="true"></span>Profit</span></span></div>
    <div class="flex items-end gap-1 h-48 overflow-x-auto" role="img" aria-label="Bar chart of daily sales and profit">
        @foreach ($days as $d)
            <div class="flex-1 min-w-3 h-full flex flex-col justify-end items-center gap-1" title="{{ $d['date'] }} · sales {{ money($d['sales']) }} · profit {{ money($d['profit']) }}">
                <div class="w-full rounded-t bg-brand-soft relative" style="height: {{ max(2, round($d['sales'] / $max * 100)) }}%">
                    <div class="absolute bottom-0 inset-x-0 rounded-t bg-brand" style="height: {{ $d['sales'] > 0 ? max(0, round($d['profit'] / max($d['sales'], 1) * 100)) : 0 }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="flex justify-between text-[11px] text-muted"><span>{{ $days[0]['date'] ?? '' }}</span><span>{{ end($days)['date'] ?? '' }}</span></div>
</section>
@endif

<div class="grid gap-5 grid-cols-[repeat(auto-fit,minmax(min(380px,100%),1fr))]">
    <section class="card overflow-hidden">
        <h2 class="m-0 px-5 py-4 text-lg font-bold">Top services</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Service</th><th>Orders</th><th class="text-right">Sales</th><th class="text-right">Profit</th></tr></thead>
            <tbody>
            @forelse ($topServices as $s)
                <tr><td>{{ $s->service_name }}</td><td>{{ $s->n }}</td><td class="text-right whitespace-nowrap">{{ money($s->sales) }}</td><td class="text-right whitespace-nowrap text-ok">{{ money($s->profit) }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-6">No completed orders.</td></tr>
            @endforelse
            </tbody>
        </table></div>
<div class="p-3">{{ $topServices->links() }}</div>
    </section>

    <section class="card overflow-hidden">
        <h2 class="m-0 px-5 py-4 text-lg font-bold">Top customers</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>User</th><th>Orders</th><th class="text-right">Sales</th></tr></thead>
            <tbody>
            @forelse ($topUsers as $u)
                <tr><td><a href="{{ route('admin.users.show', $u->id) }}">{{ $u->username }}</a></td><td>{{ $u->n }}</td><td class="text-right whitespace-nowrap">{{ money($u->sales) }}</td></tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted py-6">No data.</td></tr>
            @endforelse
            </tbody>
        </table></div>
<div class="p-3">{{ $topUsers->links() }}</div>
    </section>

    <section class="card overflow-hidden">
        <h2 class="m-0 px-5 py-4 text-lg font-bold">Top-ups by method</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Method</th><th>Count</th><th class="text-right">Total</th></tr></thead>
            <tbody>
            @forelse ($byMethod as $m)
                <tr><td>{{ $m->method }}</td><td>{{ $m->n }}</td><td class="text-right whitespace-nowrap">{{ money($m->total) }}</td></tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted py-6">No approved top-ups.</td></tr>
            @endforelse
            </tbody>
        </table></div>
<div class="p-3">{{ $byMethod->links() }}</div>
    </section>
</div>

<section id="daily-report" class="card overflow-hidden">
    <h2 class="m-0 px-5 py-4 text-lg font-bold">By day</h2>
    <div class="table-wrap"><table class="table min-w-[720px]">
        <thead><tr><th>Date</th><th>Placed</th><th>Done</th><th>Rejected</th><th class="text-right">Sales</th><th class="text-right">Profit</th><th class="text-right">Top-ups</th></tr></thead>
        <tbody>
        @foreach ($dailyReport as $d)
            <tr>
                <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($d['date'])->format('D d M') }}</td>
                <td>{{ $d['placed'] }}</td><td>{{ $d['success'] }}</td><td>{{ $d['rejected'] }}</td>
                <td class="text-right whitespace-nowrap">{{ money($d['sales']) }}</td>
                <td class="text-right whitespace-nowrap text-ok">{{ money($d['profit']) }}</td>
                <td class="text-right whitespace-nowrap">{{ money($d['deposits']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="p-4 border-t border-line">
        <div class="text-xs text-muted mb-3">Showing {{ $dailyReport->firstItem() ?? 0 }}–{{ $dailyReport->lastItem() ?? 0 }} of {{ $dailyReport->total() }} days</div>
        {{ $dailyReport->onEachSide(1)->links('partials.numbered-pagination') }}
    </div>
</section>
@endsection
