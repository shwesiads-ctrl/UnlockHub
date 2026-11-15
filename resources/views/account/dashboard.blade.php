@extends('layouts.account', ['title' => 'Mingalaba, '.auth()->user()->name])

@section('content')
@if (! $user->hasTwoFactor())
    <div class="rounded-xl px-4 py-3 text-sm bg-brand-soft text-brand-ink flex flex-wrap gap-3 items-center">
        <span class="flex-1">Protect your wallet — turn on Google Authenticator.</span>
        <a href="{{ route('account.security') }}" class="btn btn-primary h-9">Set up 2FA</a>
    </div>
@endif

@if (app(\App\Services\PointService::class)->canCheckInToday($user))
    <form method="POST" action="{{ route('account.points.checkin') }}" class="rounded-xl px-4 py-3 text-sm bg-point-soft text-point flex flex-wrap gap-3 items-center">
        @csrf
        <span class="flex-1 font-medium">Daily check-in is ready{{ $user->checkin_streak > 0 && $user->last_checkin_on?->isSameDay(today()->subDay()) ? ' — keep your '.$user->checkin_streak.'-day streak!' : '.' }}</span>
        <button class="btn btn-primary h-9">Check in</button>
    </form>
@endif

<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
    <div class="bg-brand text-white rounded-2xl p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold opacity-90">Wallet balance</span>
        <span class="font-display text-[30px] font-bold">{{ money($user->balance) }}</span>
        <a href="{{ route('account.funds.index') }}" class="text-white hover:text-white text-[13px] font-semibold">Add funds →</a>
    </div>
    @if (\App\Services\PointService::systemOn())
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">Points</span>
        <span class="font-display text-[30px] font-bold text-point">{{ number_format($user->points) }} pts</span>
        <span class="text-[13px] text-muted">= {{ money($pointsValue) }} credit · <a href="{{ route('account.points') }}">Redeem</a></span>
    </div>
    @endif
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">Orders in process</span>
        <span class="font-display text-[30px] font-bold">{{ $stats['open'] }}</span>
    </div>
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">Completed this month</span>
        <span class="font-display text-[30px] font-bold text-ok">{{ $stats['success_month'] }}</span>
        <span class="text-[13px] text-muted">{{ $stats['rejected_month'] }} rejected · refunded</span>
    </div>
</div>

<section class="card overflow-hidden">
    <div class="flex justify-between items-center px-5 py-4">
        <h2 class="m-0 text-lg font-bold">Recent orders</h2>
    </div>
    @if ($orders->isEmpty())
        <p class="m-0 px-5 pb-6 text-sm text-muted">No orders yet. Browse services to place your first order.</p>
    @else
        <div class="table-wrap">
            <table class="table min-w-[620px]">
                <thead><tr><th>Order</th><th>Service</th><th>Result</th><th>Status</th></tr></thead>
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
    <h2 class="m-0 text-lg font-bold">Invite friends</h2>
    <p class="m-0 text-sm text-muted">Share your link — you earn {{ setting('points.referral_bonus', 50) }} points when they complete their first order.</p>
    @php $refLink = route('register', ['ref' => $user->referral_code]); @endphp
    <div class="flex flex-wrap gap-2">
        <label for="reflink" class="sr-only">Your referral link</label>
        <input id="reflink" type="text" readonly value="{{ $refLink }}" class="input flex-[1_1_280px] h-11 font-mono text-sm">
        <button type="button" class="btn" data-copy="{{ $refLink }}">Copy</button>
    </div>
</section>
@endsection
