@extends('layouts.account', ['title' => 'Points'])

@section('content')
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
    <div class="rounded-2xl p-5 flex flex-col gap-2 bg-point-soft">
        <span class="text-[13px] font-semibold text-point">{{ __('Your points') }}</span>
        <span class="font-display text-[32px] font-bold text-point">{{ number_format($user->points) }}</span>
        <span class="text-[13px] text-point">= {{ money($user->points * $rules['redeem_rate']) }} {{ __('credit') }}</span>
    </div>
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">{{ __('Earned all time') }}</span>
        <span class="font-display text-[32px] font-bold">{{ number_format($lifetime) }}</span>
        <span class="text-[13px] text-muted">{{ __('Group') }}: {{ $user->group?->name ?? '—' }}</span>
    </div>
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">{{ __('Friends referred') }}</span>
        <span class="font-display text-[32px] font-bold">{{ $referrals->total() }}</span>
        <span class="text-[13px] text-muted">{{ __(':count have ordered', ['count' => $referralsWithOrders]) }}</span>
    </div>
    @if ($nextGroup)
        @php $pct = min(100, (int) round($lifetime / max(1, $nextGroup->min_points_for_auto_upgrade) * 100)); @endphp
        <div class="card p-5 flex flex-col gap-2">
            <span class="text-[13px] font-semibold text-muted">{{ __('Next: :group prices', ['group' => $nextGroup->name]) }}</span>
            <span class="font-display text-[22px] font-bold">{{ __(':points pts to go', ['points' => number_format($nextGroup->min_points_for_auto_upgrade - $lifetime)]) }}</span>
            <div class="h-2 rounded-full bg-surface-2 overflow-hidden" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Progress to :group', ['group' => $nextGroup->name]) }}">
                <div class="h-full bg-brand" style="width: {{ $pct }}%"></div>
            </div>
        </div>
    @endif
</div>

<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_520px] min-w-0 card overflow-hidden">
        <h2 class="m-0 px-5 py-4 text-lg font-bold">{{ __('Points history') }}</h2>
        <div class="table-wrap">
            <table class="table min-w-[560px]">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Details') }}</th><th class="text-right">{{ __('Points') }}</th><th class="text-right">{{ __('Balance') }}</th></tr></thead>
                <tbody>
                @forelse ($history as $t)
                    <tr>
                        <td class="whitespace-nowrap text-muted">{{ $t->created_at->format('d M Y H:i') }}</td>
                        <td><span @class(['badge', 'badge-success' => $t->points > 0, 'badge-rejected' => $t->points < 0])>{{ $types[$t->type] ?? $t->type }}</span></td>
                        <td>{{ $t->description }}</td>
                        <td @class(['text-right font-semibold whitespace-nowrap', 'text-ok' => $t->points > 0, 'text-danger' => $t->points < 0])>{{ $t->points > 0 ? '+' : '−' }}{{ number_format(abs($t->points)) }}</td>
                        <td class="text-right whitespace-nowrap">{{ number_format($t->points_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-10">{{ __('No points yet — every successful order earns points.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $history->links() }}</div>
    </section>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">
        @if ($rules['checkin_points'] > 0)
            <section class="card p-5 flex flex-col gap-3">
                <div class="flex justify-between items-center">
                    <h2 class="m-0 text-lg font-bold">{{ __('Daily check-in') }}</h2>
                    @if ($user->checkin_streak > 0 && $user->last_checkin_on?->gte(today()->subDay()))
                        <span class="badge badge-pending">{{ __(':days-day streak', ['days' => $user->checkin_streak]) }}</span>
                    @endif
                </div>
                @php
                    $nextStreak = $user->last_checkin_on?->isSameDay(today()->subDay()) ? $user->checkin_streak + 1 : 1;
                    $nextPoints = $rules['checkin_points'] + (min($nextStreak, max(1, $rules['checkin_streak_max'])) - 1) * $rules['checkin_streak_bonus'];
                @endphp
                <div class="flex gap-1.5" aria-label="{{ __('Streak progress') }}">
                    @for ($d = 1; $d <= max(1, $rules['checkin_streak_max']); $d++)
                        @php $done = $canCheckIn ? $d < $nextStreak : $d <= $user->checkin_streak; @endphp
                        <span @class(['flex-1 h-9 rounded-lg flex items-center justify-center text-xs font-bold', 'bg-point text-white' => $done, 'bg-surface-2 text-muted' => ! $done]) title="{{ __('Day :n', ['n' => $d]) }}">{{ $d }}</span>
                    @endfor
                </div>
                @if ($canCheckIn)
                    <form method="POST" action="{{ route('account.points.checkin') }}">@csrf<button class="btn btn-primary w-full">{{ __('Check in') }} · +{{ $nextPoints }} {{ __('pts') }}</button></form>
                @else
                    <p class="m-0 text-sm text-muted text-center">{{ __('Checked in today. Come back tomorrow to keep your streak.') }}</p>
                @endif
            </section>
        @endif

        <form method="POST" action="{{ route('account.points.redeem') }}" class="card p-5 flex flex-col gap-3.5" data-redeem data-rate="{{ $rules['redeem_rate'] }}">
            @csrf
            <h2 class="m-0 text-lg font-bold flex items-center gap-2"><x-icon name="star" class="text-point" />{{ __('Redeem points') }}</h2>
            <p class="m-0 text-sm text-muted">{{ __('1 point = :value. Minimum :min points.', ['value' => money($rules['redeem_rate']), 'min' => number_format($rules['redeem_min'])]) }}</p>
            @if ($rules['redeem_rate'] > 0 && $user->points >= $rules['redeem_min'])
                <div>
                    <label for="points" class="label">{{ __('Points to redeem') }}</label>
                    <input id="points" name="points" type="number" min="{{ $rules['redeem_min'] }}" max="{{ $user->points }}" step="1" value="{{ old('points', $user->points) }}" class="input" required>
                    <x-field-error name="points" />
                </div>
                <div class="flex justify-between text-sm p-3 rounded-[10px] bg-point-soft text-point font-semibold"><span>{{ __('You get') }}</span><span data-redeem-value>{{ money(old('points', $user->points) * $rules['redeem_rate']) }}</span></div>
                <button class="btn btn-primary">{{ __('Convert to wallet credit') }}</button>
            @elseif ($rules['redeem_rate'] <= 0)
                <div class="rounded-[10px] px-3 py-2.5 bg-surface-2 text-sm text-muted">{{ __('Redeeming points is turned off right now. Your points are kept.') }}</div>
            @else
                <div class="rounded-[10px] px-3 py-2.5 bg-surface-2 text-sm text-muted">
                    {{ __('You need :points more points to redeem.', ['points' => number_format(max(0, $rules['redeem_min'] - $user->points))]) }}
                </div>
            @endif
        </form>

        <section id="convert-wallet" class="card p-5 flex flex-col gap-3.5">
            <div>
                <h2 class="m-0 text-lg font-bold">{{ __('Convert wallet Ks to points') }}</h2>
                @if (\App\Services\PointService::on('wallet_convert') && $rules['convert_rate'] > 0)
                    <p class="m-0 mt-1 text-sm text-muted">{{ __('Every :ks Ks converts to 1 point.', ['ks' => number_format($rules['convert_rate'])]) }}</p>
                @endif
            </div>
            @if (! \App\Services\PointService::on('wallet_convert') || $rules['convert_rate'] <= 0)
                <div class="rounded-[10px] px-3 py-2.5 bg-surface-2 text-sm text-muted">{{ __('Wallet-to-points conversion is turned off right now. Your wallet balance and points are safe.') }}</div>
            @elseif ((int) floor((float) $user->balance) < $rules['convert_rate'])
                <div class="rounded-[10px] px-3 py-2.5 bg-surface-2 text-sm text-muted">{{ __('You need at least :amount in your wallet to convert.', ['amount' => money($rules['convert_rate'])]) }}</div>
            @else
                <form method="POST" action="{{ route('account.points.convert-wallet') }}" class="flex flex-col gap-3" data-wallet-convert data-rate="{{ $rules['convert_rate'] }}" data-balance="{{ (float) $user->balance }}">
                    @csrf
                    <div>
                        <label for="ks_amount" class="label">{{ __('Ks to convert') }}</label>
                        <input id="ks_amount" name="ks_amount" type="number" min="{{ $rules['convert_rate'] }}" max="{{ (int) floor((float) $user->balance) }}" step="{{ $rules['convert_rate'] }}" value="{{ old('ks_amount', $rules['convert_rate']) }}" class="input h-11" required>
                        <x-field-error name="ks_amount" />
                    </div>
                    <div class="flex justify-between text-sm p-3 rounded-[10px] bg-point-soft text-point font-semibold"><span>{{ __('Points you get') }}</span><span data-wallet-convert-value>{{ number_format((int) floor((float) old('ks_amount', $rules['convert_rate']) / $rules['convert_rate'])) }} {{ __('pts') }}</span></div>
                    <p class="m-0 text-xs text-muted">{{ __('Wallet balance after conversion:') }} <span data-wallet-after>{{ money(max(0, (float) $user->balance - (float) old('ks_amount', $rules['convert_rate']))) }}</span></p>
                    <button class="btn btn-primary">{{ __('Convert Ks to points') }}</button>
                </form>
            @endif
        </section>

        <section class="card p-5 flex flex-col gap-3">
            <h2 class="m-0 text-lg font-bold">{{ __('Invite friends') }}</h2>
            <p class="m-0 text-sm text-muted">{{ __('You get') }} <strong class="text-point">{{ number_format($rules['referral_bonus']) }} {{ __('points') }}</strong> {{ __('when a friend you invite completes their first order.') }}</p>
            @php $refLink = route('register', ['ref' => $user->referral_code]); @endphp
            <label for="reflink" class="sr-only">{{ __('Your referral link') }}</label>
            <input id="reflink" type="text" readonly value="{{ $refLink }}" class="input h-11 font-mono text-sm">
            <div class="flex gap-2">
                <button type="button" class="btn flex-1" data-copy="{{ $refLink }}">{{ __('Copy link') }}</button>
                <button type="button" class="btn flex-1" data-copy="{{ $user->referral_code }}">{{ __('Copy code') }}: {{ $user->referral_code }}</button>
            </div>
            @if ($referrals->isNotEmpty())
                <ul class="m-0 p-0 list-none flex flex-col gap-1.5 mt-1">
                    @foreach ($referrals as $r)
                        <li class="flex justify-between text-sm px-3 py-2 rounded-lg bg-surface-2">
                            <span>{{ $r->username }}</span>
                            @if ($r->success_orders > 0)
                                <span class="text-ok font-semibold">{{ __('ordered ✓') }}</span>
                            @else
                                <span class="text-muted">{{ __('joined :date', ['date' => $r->created_at->format('d M')]) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                {{ $referrals->links() }}
            @endif
        </section>

        <section class="card p-5 flex flex-col gap-2.5 text-sm">
            <h2 class="m-0 text-lg font-bold">{{ __('Ways to earn') }}</h2>
            @php
                $ways = array_filter([
                    ['Successful order', 'about 1 pt per '.number_format($rules['per_ks']).' Ks (shown on each service)', $rules['per_ks'] > 0],
                    ['Daily check-in', '+'.$rules['checkin_points'].' pts, more for streaks', $rules['checkin_points'] > 0],
                    ['Top-up bonus', '1 pt per '.number_format(max(1, $rules['topup_per_ks'])).' Ks topped up', $rules['topup_per_ks'] > 0],
                    ['Complete your profile', '+'.$rules['profile_bonus'].' pts once', $rules['profile_bonus'] > 0 && ! $user->profile_bonus_awarded],
                    ['Invite a friend', '+'.$rules['referral_bonus'].' pts after their first order', $rules['referral_bonus'] > 0],
                ], fn ($w) => $w[2]);
            @endphp
            @foreach ($ways as [$title, $desc])
                <div class="flex flex-col p-3 rounded-[10px] bg-surface-2"><span class="font-semibold">{{ $title }}</span><span class="text-xs text-muted">{{ $desc }}</span></div>
            @endforeach
            @if ($rules['profile_bonus'] > 0 && ! $user->profile_bonus_awarded)
                <a href="{{ route('account.profile') }}" class="text-[13px]">{{ __('Complete your profile →') }}</a>
            @endif
            <span class="text-xs text-muted">{{ __('Rejected or cancelled orders earn nothing.') }}@if ($nextGroup) {{ __('Earn enough and you move up to cheaper group prices automatically.') }}@endif</span>
        </section>
    </aside>
</div>
@endsection
