@extends('layouts.account', ['title' => __('Earnings')])

@section('content')
@if ($user->isReseller()) @include('reseller._nav') @endif
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
    @if (\App\Services\AffiliateService::enabled())
        <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Commission') }}</span><span class="font-display text-2xl font-bold text-ok">{{ money($totals['commission'] ?? 0) }}</span><span class="text-xs text-muted">{{ $referrals }} {{ __('people joined with your link') }}</span></div>
    @endif
    @if ($user->isReseller())
        <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Reseller profit') }}</span><span class="font-display text-2xl font-bold text-ok">{{ money($totals['reseller_profit'] ?? 0) }}</span></div>
    @endif
    <div class="card p-5 flex flex-col gap-1"><span class="text-[13px] text-muted font-semibold">{{ __('Can withdraw') }}</span><span class="font-display text-2xl font-bold">{{ money($withdrawable) }}</span><span class="text-xs text-muted">{{ __('Earnings stay in your wallet until you withdraw.') }}</span></div>
</div>

@if (\App\Services\AffiliateService::enabled())
    <section class="card p-5 flex flex-col gap-2">
        <h2 class="m-0 text-lg font-bold">{{ __('Your referral link') }}</h2>
        <p class="m-0 text-sm text-muted">{{ __('Earn') }} <strong>{{ (float) setting('affiliate.percent') }}%</strong> {{ setting('affiliate.basis', 'profit') === 'sale' ? __('of every order') : __('of our profit on every order') }} {{ __('by people who sign up with your link') }}@if ((int) setting('affiliate.months', 0) > 0) ({{ __('for') }} {{ (int) setting('affiliate.months') }} {{ __('months') }})@endif.</p>
        <div class="flex gap-2"><input readonly value="{{ route('register', ['ref' => $user->referral_code]) }}" class="input h-10 font-mono text-sm" aria-label="{{ __('Referral link') }}"><button type="button" class="btn h-10" data-copy="{{ route('register', ['ref' => $user->referral_code]) }}">{{ __('Copy') }}</button></div>
    </section>
@endif

<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_480px] min-w-0 card table-wrap">
        <table class="table min-w-[480px]">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Details') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead>
            <tbody>
            @forelse ($earnings as $e)
                <tr><td class="text-muted whitespace-nowrap">{{ $e->created_at->format('d M Y H:i') }}</td><td>{{ $e->description }}</td><td class="text-right text-ok font-semibold">+{{ money($e->amount) }}</td></tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted py-8">{{ __('No earnings yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <aside class="flex-[1_1_300px] min-w-0 flex flex-col gap-4">
        <form method="POST" action="{{ route('account.earnings.withdraw') }}" class="card p-5 flex flex-col gap-3" data-submit-once>
            @csrf
            <h2 class="m-0 text-lg font-bold">{{ __('Withdraw') }}</h2>
            <div><label for="w_amt" class="label text-xs">{{ __('Amount (Ks)') }} · {{ __('min') }} {{ money(setting('affiliate.withdraw_min', 10000)) }}</label><input id="w_amt" name="amount" type="number" min="1" step="1" max="{{ (int) $withdrawable }}" value="{{ old('amount') }}" class="input h-10" required></div>
            <div><label for="w_m" class="label text-xs">{{ __('Pay to') }}</label><select id="w_m" name="method" class="input h-10">@foreach ($methods as $m)<option @selected(old('method') === $m)>{{ $m }}</option>@endforeach</select></div>
            <div><label for="w_acc" class="label text-xs">{{ __('Account / phone + name') }}</label><input id="w_acc" name="account" value="{{ old('account') }}" maxlength="120" class="input h-10" required></div>
            <button class="btn btn-primary" @disabled($withdrawable < (float) setting('affiliate.withdraw_min', 10000))>{{ __('Request withdrawal') }}</button>
        </form>
        @if ($withdrawals->isNotEmpty())
            <section class="card p-5 flex flex-col gap-2 text-[13px]">
                <h2 class="m-0 text-base font-bold">{{ __('Withdrawals') }}</h2>
                @foreach ($withdrawals as $w)
                    <div class="flex justify-between gap-2 items-center">
                        <span class="text-muted">{{ $w->created_at->format('d M') }}</span><span>{{ money($w->amount) }} · {{ $w->method }}</span>
                        <span @class(['badge', 'badge-pending' => $w->status === 'pending', 'badge-success' => $w->status === 'paid', 'badge-rejected' => $w->status === 'rejected'])>{{ ucfirst($w->status) }}</span>
                    </div>
                    @if ($w->status === 'rejected' && $w->admin_note) <div class="text-xs text-danger">{{ $w->admin_note }}</div> @endif
                @endforeach
            </section>
        @endif
    </aside>
</div>
{{ $earnings->links() }}
@endsection
