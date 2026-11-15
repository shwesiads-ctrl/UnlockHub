@extends('layouts.admin', ['title' => 'Dashboard', 'subtitle' => now()->format('l, d M Y')])

@section('content')
<div class="admin-overview">
<section class="dashboard-welcome">
    <div><span class="text-xs font-bold uppercase tracking-wide text-brand-ink">Store overview</span><h2 class="m-0 mt-2 text-2xl font-bold">Welcome back, {{ auth()->user()->name }}</h2><p class="m-0 mt-2 text-sm text-muted">Your sales, order queues and customer payments at a glance.</p></div>
    <div class="flex flex-wrap gap-2"><a href="{{ route('admin.services.create') }}" class="btn btn-primary">+ Add service</a><a href="{{ route('admin.reports.index') }}" class="btn">View reports →</a></div>
</section>
<div class="dashboard-stats">
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Today's sales</span><span class="font-display text-[28px] font-bold">{{ money($stats['sales_today']) }}</span><span class="text-[13px] text-ok font-semibold">Profit {{ money($stats['profit_today']) }}</span></div>
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Orders today</span><span class="font-display text-[28px] font-bold">{{ $stats['orders_today'] }}</span><span class="text-[13px] text-muted">{{ $stats['api_today'] }} API · {{ $stats['orders_today'] - $stats['api_today'] }} Manual · {{ $stats['open'] }} open</span></div>
    <a href="{{ route('admin.payments.index') }}" class="rounded-2xl p-5 flex flex-col gap-2 no-underline bg-warn-soft border border-warn text-warn hover:text-warn"><span class="text-[13px] font-semibold">Payments to approve</span><span class="font-display text-[28px] font-bold">{{ $adminBadges['payments'] ?? 0 }}</span><span class="text-[13px] font-semibold">Review receipts →</span></a>
    <a href="{{ route('admin.orders.manual') }}" class="rounded-2xl p-5 flex flex-col gap-2 no-underline bg-danger-soft border border-danger text-danger hover:text-danger"><span class="text-[13px] font-semibold">Manual queue</span><span class="font-display text-[28px] font-bold">{{ $adminBadges['manual'] ?? 0 }}</span><span class="text-[13px] font-semibold">{{ $stats['api_failed'] ? $stats['api_failed'].' from API failures →' : 'Process orders →' }}</span></a>
</div>

<div class="dashboard-main-grid">
    <section class="flex-[999_1_520px] min-w-0 card p-5 flex flex-col gap-4">
        <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Sales · last 7 days</h2><a href="{{ route('admin.reports.index', ['preset' => '7d']) }}" class="text-sm">Full report</a></div>
        <div class="dashboard-chart-summary">
            <div><span class="text-xs text-muted">Completed sales</span><strong class="block font-display text-2xl mt-1">{{ money(array_sum(array_column($week, 'sales'))) }}</strong></div>
            <div><span class="text-xs text-muted">Profit</span><strong class="block text-ok mt-1">{{ money(array_sum(array_column($week, 'profit'))) }}</strong></div>
            <span class="text-xs text-muted ml-auto">Last 7 days</span>
        </div>
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
            <div class="dashboard-empty"><span class="dashboard-empty-symbol" aria-hidden="true">↔</span><strong class="text-ink">Connect your first supplier</strong><p class="m-0 text-sm text-muted">Automate orders and monitor balances here.</p><a href="{{ route('admin.suppliers.create') }}" class="btn">Add supplier</a></div>
        @endforelse
    </section>
</div>

<div class="dashboard-detail-grid">
    <section class="card p-5 flex flex-col gap-2">
        <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Pending payments</h2><a href="{{ route('admin.payments.index') }}" class="text-sm">View all</a></div>
        @forelse ($pendingPayments as $p)
            <a href="{{ route('admin.payments.show', $p) }}" class="flex justify-between items-center gap-3 text-sm py-2.5 border-b border-line last:border-0 no-underline text-ink hover:text-brand-ink">
                <span><strong>{{ $p->user->username }}</strong> · {{ $p->method->name }} <span class="text-muted">· {{ $p->created_at->diffForHumans(null, true) }}</span></span>
                <span class="font-bold whitespace-nowrap">{{ money($p->amount) }}</span>
            </a>
        @empty
            <div class="dashboard-empty"><span class="dashboard-empty-symbol text-ok" aria-hidden="true">✓</span><strong class="text-ink">All caught up</strong><p class="m-0 text-sm text-muted">New top-ups will appear here for review.</p></div>
        @endforelse
    </section>

    <section class="card p-5 flex flex-col gap-2">
        <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Top services this week</h2><a href="{{ route('admin.services.index') }}" class="text-sm">Manage</a></div>
        @forelse ($topServices as $s)
            <div class="flex items-center gap-3 text-sm py-3 border-b border-line last:border-0"><span class="dashboard-rank">{{ $loop->iteration }}</span><span class="min-w-0 flex-1">{{ $s->service_name }}<span class="block text-xs text-muted mt-1">{{ money($s->sales) }} sales</span></span><span class="badge badge-info whitespace-nowrap">{{ $s->n }} orders</span></div>
        @empty
            <div class="dashboard-empty"><span class="dashboard-empty-symbol" aria-hidden="true">☆</span><strong class="text-ink">Your best sellers will appear here</strong><p class="m-0 text-sm text-muted">Ranked by completed orders this week.</p></div>
        @endforelse
    </section>

    <section class="card p-5 flex flex-col gap-2">
        <div class="flex justify-between items-center"><h2 class="m-0 text-lg font-bold">Recent activity</h2><a href="{{ route('admin.activity.index') }}" class="text-sm">Log</a></div>
        @forelse ($activity as $a)
            <div class="flex justify-between gap-3 text-[13px] py-2 border-b border-line last:border-0">
                <span class="min-w-0"><strong class="block">{{ $a->user?->username ?? 'system' }}</strong> <span class="text-muted break-all">{{ str_replace(['.', '_'], ' ', $a->action) }}</span></span>
                <span class="text-muted whitespace-nowrap">{{ $a->created_at->diffForHumans(null, true) }}</span>
            </div>
        @empty
            <div class="dashboard-empty"><strong class="text-ink">No recent activity</strong><p class="m-0 text-sm text-muted">Staff actions will appear here.</p></div>
        @endforelse
    </section>
</div>

<section class="dashboard-footer">
    <a href="{{ route('admin.users.index') }}" class="no-underline"><span class="text-xs text-muted">Customer wallet balances</span><strong class="block text-ink mt-1">{{ money($stats['wallets']) }}</strong></a>
    <div><span class="text-xs text-muted">New customers today</span><strong class="block text-ink mt-1">{{ number_format($stats['new_users_today']) }}</strong></div>
    <a href="{{ route('admin.orders.index') }}" class="btn ml-auto">Manage all orders →</a>
</section>
</div>
@endsection
