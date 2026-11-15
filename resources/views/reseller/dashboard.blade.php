@extends('layouts.account', ['title' => __('Reseller panel')])

@section('content')
@include('reseller._nav')
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Customers') }}</span><span class="font-display text-2xl font-bold">{{ number_format($customers) }}</span><span class="text-xs text-muted">{{ __('Their wallets') }}: {{ money($customerBalance) }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Orders this month') }}</span><span class="font-display text-2xl font-bold">{{ number_format($month['orders']) }}</span><span class="text-xs text-muted">{{ $open }} {{ __('open') }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Sales this month') }}</span><span class="font-display text-2xl font-bold">{{ money($month['sales']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Profit this month') }}</span><span class="font-display text-2xl font-bold text-ok">{{ money($month['profit']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('New customers') }}</span><span class="font-display text-2xl font-bold">{{ number_format($newCustomers) }}</span><span class="text-xs text-muted">{{ __('This month') }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Completed orders') }}</span><span class="font-display text-2xl font-bold text-ok">{{ number_format($month['completed']) }}</span><span class="text-xs text-muted">{{ number_format($rejected) }} {{ __('rejected this month') }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Can withdraw') }}</span><span class="font-display text-2xl font-bold">{{ money($withdrawable) }}</span><a href="{{ route('account.earnings') }}" class="text-xs">{{ __('Earnings') }} →</a></div>
</div>

<section class="card p-4 flex flex-wrap items-center gap-2">
    <strong class="mr-auto text-sm">{{ __('Quick actions') }}</strong>
    <a href="{{ route('reseller.customers') }}" class="btn btn-primary h-9 text-xs">{{ __('Manage customers') }}</a>
    <a href="{{ route('reseller.orders') }}" class="btn h-9 text-xs">{{ __('View orders') }}</a>
    <a href="{{ route('reseller.prices') }}" class="btn h-9 text-xs">{{ __('Set prices') }}</a>
    <a href="{{ route('reseller.branding') }}" class="btn h-9 text-xs">{{ __('Shop branding') }}</a>
    <a href="{{ route('account.earnings') }}" class="btn h-9 text-xs">{{ __('Earnings & withdrawals') }}</a>
</section>

@if ($open || $rejected || $pendingWithdrawals)
<section class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(180px,1fr))]">
    @if ($open)<a href="{{ route('reseller.orders', ['status' => 'pending']) }}" class="card p-4 flex items-center gap-3 no-underline text-ink"><span class="dashboard-rank bg-warn-soft text-warn">{{ $open }}</span><span class="text-sm"><strong class="block">{{ __('Orders need attention') }}</strong><span class="text-xs text-muted">{{ __('Pending or in process') }}</span></span></a>@endif
    @if ($rejected)<a href="{{ route('reseller.orders', ['status' => 'rejected']) }}" class="card p-4 flex items-center gap-3 no-underline text-ink"><span class="dashboard-rank bg-danger-soft text-danger">{{ $rejected }}</span><span class="text-sm"><strong class="block">{{ __('Rejected this month') }}</strong><span class="text-xs text-muted">{{ __('Review order outcomes') }}</span></span></a>@endif
    @if ($pendingWithdrawals)<a href="{{ route('account.earnings') }}" class="card p-4 flex items-center gap-3 no-underline text-ink"><span class="dashboard-rank bg-brand-soft text-brand-ink">{{ $pendingWithdrawals }}</span><span class="text-sm"><strong class="block">{{ __('Withdrawal requests') }}</strong><span class="text-xs text-muted">{{ __('Waiting for review') }}</span></span></a>@endif
</section>
@endif

<div class="card p-5 flex flex-wrap gap-3 items-center text-sm">
    <span class="flex-1 min-w-60">{{ __('Your wallet pays the site price for your customers\' orders: fund customers from your wallet in Customers, and keep enough balance yourself.') }}</span>
    <span class="font-semibold">{{ __('Your wallet') }}: {{ money($me->balance) }}</span>
    <a href="{{ route('account.funds.index') }}" class="btn h-9 text-[13px]">{{ __('Add funds') }}</a>
</div>

<section class="card p-5 flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div><h2 class="m-0 text-lg font-bold">{{ __('Sales & profit trend') }}</h2><p class="m-0 mt-1 text-xs text-muted">{{ __('Completed customer orders over the last 7 days') }}</p></div>
        <a href="{{ route('reseller.orders') }}" class="text-sm">{{ __('Review all orders') }} →</a>
    </div>
    @php $weekMax = max(1, max(array_column($week, 'sales'))); @endphp
    <div class="flex items-end gap-3 h-40" role="img" aria-label="{{ __('Sales trend over the last 7 days') }}">
        @foreach ($week as $day)
            <div class="flex-1 h-full flex flex-col items-center justify-end gap-2" title="{{ $day['date'] }} · {{ money($day['sales']) }} · {{ __('profit') }} {{ money($day['profit']) }}">
                <span class="text-[11px] text-muted">{{ $day['sales'] >= 1000000 ? number_format($day['sales'] / 1000000, 1).'M' : ($day['sales'] >= 1000 ? number_format($day['sales'] / 1000).'K' : number_format($day['sales'])) }}</span>
                <div @class(['w-full max-w-14 rounded-t-lg', 'bg-brand' => $loop->last, 'bg-brand-soft' => ! $loop->last]) style="height: {{ max(3, round($day['sales'] / $weekMax * 68)) }}%"></div>
                <span class="text-xs text-muted">{{ $day['label'] }}</span>
            </div>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-5 border-t border-line pt-3 text-xs"><span class="text-muted">{{ __('Weekly sales') }}: <strong class="text-ink">{{ money(array_sum(array_column($week, 'sales'))) }}</strong></span><span class="text-muted">{{ __('Weekly profit') }}: <strong class="text-ok">{{ money(array_sum(array_column($week, 'profit'))) }}</strong></span><span class="text-muted">{{ __('Orders completed') }}: <strong class="text-ink">{{ array_sum(array_column($week, 'orders')) }}</strong></span></div>
</section>

<section class="card table-wrap">
    <table class="table min-w-[640px]">
        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Service') }}</th><th class="text-right">{{ __('Price') }}</th><th class="text-right">{{ __('Profit') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @forelse ($recent as $o)
            <tr>
                <td class="font-mono text-[13px]">#{{ $o->order_no }}</td>
                <td>{{ $o->user?->username }}</td>
                <td>{{ \Illuminate\Support\Str::limit($o->service_name, 40) }}</td>
                <td class="text-right">{{ $o->priceLabel() }}</td>
                <td class="text-right text-ok">{{ money(((float) $o->price - (float) $o->reseller_price) * (($o->price_currency ?? 'ks') === 'points' ? max(1, (int) ($o->price_rate ?: 1)) : 1)) }}</td>
                <td><x-status-badge :status="$o->status" /></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-8">{{ __('No customer orders yet.') }} <a href="{{ route('reseller.customers') }}">{{ __('Add your first customer') }}</a></td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
