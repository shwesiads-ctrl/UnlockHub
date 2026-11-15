@extends('layouts.account', ['title' => __('Reseller panel')])

@section('content')
@include('reseller._nav')
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Customers') }}</span><span class="font-display text-2xl font-bold">{{ number_format($customers) }}</span><span class="text-xs text-muted">{{ __('Their wallets') }}: {{ money($customerBalance) }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Orders this month') }}</span><span class="font-display text-2xl font-bold">{{ number_format($month['orders']) }}</span><span class="text-xs text-muted">{{ $open }} {{ __('open') }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Sales this month') }}</span><span class="font-display text-2xl font-bold">{{ money($month['sales']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Profit this month') }}</span><span class="font-display text-2xl font-bold text-ok">{{ money($month['profit']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Can withdraw') }}</span><span class="font-display text-2xl font-bold">{{ money($withdrawable) }}</span><a href="{{ route('account.earnings') }}" class="text-xs">{{ __('Earnings') }} →</a></div>
</div>

<div class="card p-5 flex flex-wrap gap-3 items-center text-sm">
    <span class="flex-1 min-w-60">{{ __('Your wallet pays the site price for your customers\' orders: fund customers from your wallet in Customers, and keep enough balance yourself.') }}</span>
    <span class="font-semibold">{{ __('Your wallet') }}: {{ money($me->balance) }}</span>
    <a href="{{ route('account.funds.index') }}" class="btn h-9 text-[13px]">{{ __('Add funds') }}</a>
</div>

<section class="card table-wrap">
    <table class="table min-w-[640px]">
        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Service') }}</th><th class="text-right">{{ __('Price') }}</th><th class="text-right">{{ __('Profit') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @forelse ($recent as $o)
            <tr>
                <td class="font-mono text-[13px]">#{{ $o->order_no }}</td>
                <td>{{ $o->user?->username }}</td>
                <td>{{ \Illuminate\Support\Str::limit($o->service_name, 40) }}</td>
                <td class="text-right">{{ money($o->price) }}</td>
                <td class="text-right text-ok">{{ money((float) $o->price - (float) $o->reseller_price) }}</td>
                <td><x-status-badge :status="$o->status" /></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-8">{{ __('No customer orders yet.') }} <a href="{{ route('reseller.customers') }}">{{ __('Add your first customer') }}</a></td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
