@extends('layouts.account', ['title' => __('Hello, :name', ['name' => auth()->user()->name])])

@section('content')
@php $paymentCurrency = setting('catalog.price_currency', 'ks'); @endphp
@if (! $user->hasTwoFactor())
    <div class="rounded-xl px-4 py-3 text-sm bg-brand-soft text-brand-ink flex flex-wrap gap-3 items-center">
        <span class="flex-1">{{ __('Protect your wallet — turn on Google Authenticator.') }}</span>
        <a href="{{ route('account.security') }}" class="btn btn-primary h-9">{{ __('Set up 2FA') }}</a>
    </div>
@endif

@if (app(\App\Services\PointService::class)->canCheckInToday($user))
    <form method="POST" action="{{ route('account.points.checkin') }}" class="rounded-xl px-4 py-3 text-sm bg-point-soft text-point flex flex-wrap gap-3 items-center">
        @csrf
        <span class="flex-1 font-medium">{{ $user->checkin_streak > 0 && $user->last_checkin_on?->isSameDay(today()->subDay()) ? __('Daily check-in is ready — keep your :days-day streak!', ['days' => $user->checkin_streak]) : __('Daily check-in is ready.') }}</span>
        <button class="btn btn-primary h-9">{{ __('Check in') }}</button>
    </form>
@endif

<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
    @if ($paymentCurrency !== 'points')
    <div class="bg-brand text-white rounded-2xl p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold opacity-90">{{ __('Wallet balance') }}</span>
        <span class="font-display text-[30px] font-bold">{{ money($user->balance) }}</span>
        <a href="{{ route('account.funds.index') }}" class="text-white hover:text-white text-[13px] font-semibold">{{ __('Add funds →') }}</a>
    </div>
    @endif
    @if ($paymentCurrency === 'points' && \App\Services\PointService::systemOn())
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">{{ __('Points') }}</span>
        <span class="font-display text-[30px] font-bold text-point">{{ number_format($user->points) }} {{ __('pts') }}</span>
        <div class="flex flex-wrap gap-x-2 text-[13px] text-muted">
            @if ($canRedeemPoints)<span>= {{ money($pointsValue) }} {{ __('credit') }} · <a href="{{ route('account.points') }}">{{ __('Redeem') }}</a></span>@endif
            @if (! $canRedeemPoints)<a href="{{ route('account.points') }}">{{ __('Points history') }}</a>@endif
        </div>
    </div>
    @endif
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">{{ __('Orders in process') }}</span>
        <span class="font-display text-[30px] font-bold">{{ $stats['open'] }}</span>
    </div>
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">{{ __('Completed this month') }}</span>
        <span class="font-display text-[30px] font-bold text-ok">{{ $stats['success_month'] }}</span>
        <span class="text-[13px] text-muted">{{ __(':count rejected · refunded', ['count' => $stats['rejected_month']]) }}</span>
    </div>
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">{{ __('Spent this month') }}</span>
        <span class="font-display text-2xl font-bold">{{ money($stats['month_spent']) }}</span>
        <span class="text-[13px] text-muted">{{ number_format($stats['month_orders']) }} {{ __('orders placed') }}</span>
    </div>
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">{{ __('Successful outcomes') }}</span>
        <span class="font-display text-2xl font-bold text-ok">{{ $stats['success_rate'] }}%</span>
        <span class="text-[13px] text-muted">{{ __('Of completed or rejected orders') }}</span>
    </div>
</div>

<section class="card p-4 flex flex-wrap items-center gap-2">
    <strong class="mr-auto text-sm">{{ __('Quick actions') }}</strong>
    <a href="{{ route('services.index') }}" class="btn btn-primary h-9 text-xs">{{ __('Place order') }}</a>
    @if ($paymentCurrency !== 'points') <a href="{{ route('account.funds.index') }}" class="btn h-9 text-xs">{{ __('Add funds') }}</a> @endif
    <a href="{{ route('account.orders.index') }}" class="btn h-9 text-xs">{{ __('Order history') }}</a>
    <a href="{{ route('account.statement') }}" class="btn h-9 text-xs">{{ __('Statement') }}</a>
    <a href="{{ route('account.tickets.index') }}" class="btn h-9 text-xs">{{ __('Get support') }}</a>
</section>

<section class="card p-4 flex flex-wrap gap-3 items-center" data-install-app hidden>
    <span class="flex-1 text-sm">📱 {{ __('Add') }} {{ setting('site.name', config('app.name')) }} {{ __('to your home screen — faster ordering and order alerts.') }}</span>
    <button type="button" class="btn btn-primary h-10" data-install-app>{{ __('Install app') }}</button>
</section>

<section class="card p-5 flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div><h2 class="m-0 text-lg font-bold">{{ __('Your activity') }}</h2><p class="m-0 mt-1 text-xs text-muted">{{ __('Orders placed over the last 7 days') }}</p></div>
        <a href="{{ route('account.orders.index') }}" class="text-sm">{{ __('View all orders') }} →</a>
    </div>
    @php $weekMax = max(1, max(array_column($week, 'orders'))); @endphp
    <div class="flex items-end gap-3 h-36" role="img" aria-label="{{ __('Order activity over the last 7 days') }}">
        @foreach ($week as $day)
            <div class="flex-1 h-full flex flex-col items-center justify-end gap-2" title="{{ $day['date'] }} · {{ $day['orders'] }} {{ __('orders') }}">
                <span class="text-xs text-muted">{{ $day['orders'] }}</span>
                <div @class(['w-full max-w-14 rounded-t-lg', 'bg-brand' => $loop->last, 'bg-brand-soft' => ! $loop->last]) style="height: {{ max(3, round($day['orders'] / $weekMax * 68)) }}%"></div>
                <span class="text-xs text-muted">{{ $day['label'] }}</span>
            </div>
        @endforeach
    </div>
</section>

<section class="card p-5 flex flex-col gap-3">
    <div class="flex justify-between items-center gap-3">
        <h2 class="m-0 text-lg font-bold">★ {{ __('Favourite services') }}</h2>
        <a href="{{ route('account.favorites') }}" class="text-sm">{{ __('All favourites') }}</a>
    </div>
    @if ($favorites->isEmpty())
        <p class="m-0 text-sm text-muted">{{ __('Tap ☆ next to a service to keep it here for quick ordering.') }}</p>
    @else
        <div class="grid gap-3 grid-cols-[repeat(auto-fill,minmax(220px,1fr))]">
            @foreach ($favorites as $f)
                <a href="{{ route('services.show', $f) }}" class="rounded-xl border border-line bg-bg p-3 flex flex-col gap-1 no-underline text-ink hover:border-brand">
                    <span class="font-semibold text-sm leading-snug">{{ \Illuminate\Support\Str::limit($f->name, 70) }}</span>
                    <span class="text-xs text-muted">{{ $f->delivery_time ?: '—' }}</span>
                    <span class="font-display font-bold">{{ $f->formattedPriceFor($user) }}</span>
                </a>
            @endforeach
        </div>
    @endif
</section>

<section class="card overflow-hidden">
    <div class="flex justify-between items-center px-5 py-4">
        <h2 class="m-0 text-lg font-bold">{{ __('Recent orders') }}</h2>
    </div>
    @if ($orders->isEmpty())
        <p class="m-0 px-5 pb-6 text-sm text-muted">{{ __('No orders yet. Browse services to place your first order.') }}</p>
    @else
        <div class="table-wrap">
            <table class="table min-w-[620px]">
                <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Service') }}</th><th>{{ __('Result') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td class="whitespace-nowrap"><a href="{{ route('account.orders.show', $order) }}" class="font-semibold">#{{ $order->order_no }}</a><div class="text-xs text-muted">{{ $order->created_at->format('d M H:i') }}</div></td>
                        <td><div class="font-medium">{{ $order->service_name }}</div><div class="text-xs text-muted font-mono">{{ $order->imei }}</div></td>
                        <td class="font-mono text-[13px] text-muted">{{ $order->result ? \Illuminate\Support\Str::limit($order->result, 40) : '—' }}</td>
                        <td><x-status-badge :status="$order->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="card p-5 flex flex-col gap-2">
    <h2 class="m-0 text-lg font-bold">{{ __('Invite friends') }}</h2>
    <p class="m-0 text-sm text-muted">{{ __('Share your link — you earn :points points when they complete their first order.', ['points' => setting('points.referral_bonus', 50)]) }}</p>
    @php $refLink = route('register', ['ref' => $user->referral_code]); @endphp
    <div class="flex flex-wrap gap-2">
        <label for="reflink" class="sr-only">{{ __('Your referral link') }}</label>
        <input id="reflink" type="text" readonly value="{{ $refLink }}" class="input flex-[1_1_280px] h-11 font-mono text-sm">
        <button type="button" class="btn" data-copy="{{ $refLink }}">{{ __('Copy') }}</button>
    </div>
</section>
@endsection
