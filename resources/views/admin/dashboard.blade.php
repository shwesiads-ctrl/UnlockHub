@extends('layouts.admin', ['title' => 'Dashboard', 'subtitle' => now()->format('l, d M Y')])

@section('content')
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Today's sales</span><span class="font-display text-[28px] font-bold">{{ money($stats['sales_today']) }}</span><span class="text-[13px] text-ok font-semibold">Profit {{ money($stats['profit_today']) }}</span></div>
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Orders today</span><span class="font-display text-[28px] font-bold">{{ $stats['orders_today'] }}</span><span class="text-[13px] text-muted">{{ $stats['api_today'] }} API · {{ $stats['orders_today'] - $stats['api_today'] }} Manual · {{ $stats['open'] }} open</span></div>
    <a href="{{ route('admin.payments.index') }}" class="rounded-2xl p-5 flex flex-col gap-2 no-underline bg-warn-soft border border-warn text-warn hover:text-warn"><span class="text-[13px] font-semibold">Payments to approve</span><span class="font-display text-[28px] font-bold">{{ $adminBadges['payments'] ?? 0 }}</span><span class="text-[13px] font-semibold">Review receipts →</span></a>
    <a href="{{ route('admin.orders.manual') }}" class="rounded-2xl p-5 flex flex-col gap-2 no-underline bg-danger-soft border border-danger text-danger hover:text-danger"><span class="text-[13px] font-semibold">Manual queue</span><span class="font-display text-[28px] font-bold">{{ $adminBadges['manual'] ?? 0 }}</span><span class="text-[13px] font-semibold">{{ $stats['api_failed'] ? $stats['api_failed'].' from API failures →' : 'Process orders →' }}</span></a>
</div>

<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_520px] min-w-0 card p-5 flex flex-col gap-4">
        <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Sales · last 7 days</h2><a href="{{ route('admin.reports.index', ['preset' => '7d']) }}" class="text-sm">Full report</a></div>
        @php $max = max(1, max(array_column($week, 'sales'))); @endphp
        <div class="flex items-end gap-3.5 h-52 pt-2" role="img" aria-label="Bar chart of completed sales for the last 7 days">
            @foreach ($week as $d)
                <div class="flex-1 h-full flex flex-col items-center justify-end gap-2" title="{{ $d['date'] }} · {{ money($d['sales']) }} · profit {{ money($d['profit']) }}">
                    <span class="text-[11px] text-muted font-semibold">{{ $d['sales'] >= 1000000 ? number_format($d['sales'] / 1000000, 1).'M' : ($d['sales'] >= 1000 ? number_format($d['sales'] / 1000).'K' : number_format($d['sales'])) }}</span>
                    <div @class(['w-full max-w-14 rounded-t-lg', 'bg-brand' => $loop->last, 'bg-brand-soft' => ! $loop->last]) style="height: {{ max(2, round($d['sales'] / $max * 75)) }}%"></div>
                    <span class="text-xs text-muted">{{ $d['label'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="flex-[1_1_320px] min-w-0 card p-5 flex flex-col gap-3">
        <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Supplier balances</h2><a href="{{ route('admin.suppliers.index') }}" class="text-sm">Manage</a></div>
        @forelse ($suppliers as $s)
            <div class="flex justify-between items-center gap-3 p-3 rounded-[10px] bg-surface-2">
                <div class="flex flex-col gap-0.5 min-w-0">
                    <span class="font-semibold text-sm truncate">{{ $s->name }}</span>
                    @if ($s->status === 'error')
                        <span class="text-xs text-danger font-semibold truncate" title="{{ $s->last_error }}">● {{ \Illuminate\Support\Str::limit($s->last_error, 40) }}</span>
                    @elseif ($s->status === 'disabled')
                        <span class="text-xs text-muted font-semibold">● Disabled</span>
                    @elseif ($s->balance !== null && (float) $s->balance < (float) setting('telegram.low_balance', 20))
                        <span class="text-xs text-warn font-semibold">● Low balance</span>
                    @else
                        <span class="text-xs text-ok font-semibold">● Connected</span>
                    @endif
                </div>
                <span class="font-display font-bold whitespace-nowrap">{{ $s->balance !== null ? number_format((float) $s->balance, 2).' '.$s->currency : '—' }}</span>
            </div>
        @empty
            <p class="m-0 text-sm text-muted">No suppliers yet. <a href="{{ route('admin.suppliers.create') }}">Add one</a> to automate orders.</p>
        @endforelse
    </section>
</div>

<div class="grid gap-5 grid-cols-[repeat(auto-fit,minmax(320px,1fr))]">
    <section class="card p-5 flex flex-col gap-2">
        <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Pending payments</h2><a href="{{ route('admin.payments.index') }}" class="text-sm">View all</a></div>
        @forelse ($pendingPayments as $p)
            <a href="{{ route('admin.payments.show', $p) }}" class="flex justify-between items-center gap-3 text-sm py-2.5 border-b border-line last:border-0 no-underline text-ink hover:text-brand-ink">
                <span><strong>{{ $p->user->username }}</strong> · {{ $p->method->name }} <span class="text-muted">· {{ $p->created_at->diffForHumans(null, true) }}</span></span>
                <span class="font-bold whitespace-nowrap">{{ money($p->amount) }}</span>
            </a>
        @empty
            <p class="m-0 text-sm text-muted">All caught up.</p>
        @endforelse
    </section>

    <section class="card p-5 flex flex-col gap-2">
        <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Top services this week</h2><a href="{{ route('admin.services.index') }}" class="text-sm">Manage</a></div>
        @forelse ($topServices as $s)
            <div class="flex justify-between gap-3 text-sm py-2.5 border-b border-line last:border-0"><span>{{ $s->service_name }}</span><span class="text-muted whitespace-nowrap">{{ $s->n }} orders</span></div>
        @empty
            <p class="m-0 text-sm text-muted">No completed orders this week.</p>
        @endforelse
    </section>

    <section class="card p-5 flex flex-col gap-2">
        <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Recent activity</h2><a href="{{ route('admin.activity.index') }}" class="text-sm">Log</a></div>
        @forelse ($activity as $a)
            <div class="flex justify-between gap-3 text-[13px] py-2 border-b border-line last:border-0">
                <span><strong>{{ $a->user?->username ?? 'system' }}</strong> <span class="font-mono text-muted">{{ $a->action }}</span></span>
                <span class="text-muted whitespace-nowrap">{{ $a->created_at->diffForHumans(null, true) }}</span>
            </div>
        @empty
            <p class="m-0 text-sm text-muted">Nothing yet.</p>
        @endforelse
    </section>
</div>

<p class="m-0 text-xs text-muted">{{ money($stats['wallets']) }} held in user wallets · {{ $stats['new_users_today'] }} new users today</p>
@endsection
