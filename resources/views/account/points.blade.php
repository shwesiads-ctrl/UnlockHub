@extends('layouts.account', ['title' => 'Points'])

@section('content')
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
    <div class="rounded-2xl p-5 flex flex-col gap-2 bg-point-soft">
        <span class="text-[13px] font-semibold text-point">Your points</span>
        <span class="font-display text-[32px] font-bold text-point">{{ number_format($user->points) }}</span>
        <span class="text-[13px] text-point">= {{ money($user->points * $rules['redeem_rate']) }} credit</span>
    </div>
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">Earned all time</span>
        <span class="font-display text-[32px] font-bold">{{ number_format($lifetime) }}</span>
        <span class="text-[13px] text-muted">Group: {{ $user->group?->name ?? '—' }}</span>
    </div>
    <div class="card p-5 flex flex-col gap-2">
        <span class="text-[13px] font-semibold text-muted">Friends referred</span>
        <span class="font-display text-[32px] font-bold">{{ $referrals->count() }}</span>
        <span class="text-[13px] text-muted">{{ $referrals->where('success_orders', '>', 0)->count() }} have ordered</span>
    </div>
    @if ($nextGroup)
        @php $pct = min(100, (int) round($lifetime / max(1, $nextGroup->min_points_for_auto_upgrade) * 100)); @endphp
        <div class="card p-5 flex flex-col gap-2">
            <span class="text-[13px] font-semibold text-muted">Next: {{ $nextGroup->name }} prices</span>
            <span class="font-display text-[22px] font-bold">{{ number_format($nextGroup->min_points_for_auto_upgrade - $lifetime) }} pts to go</span>
            <div class="h-2 rounded-full bg-surface-2 overflow-hidden" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress to {{ $nextGroup->name }}">
                <div class="h-full bg-brand" style="width: {{ $pct }}%"></div>
            </div>
        </div>
    @endif
</div>

<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_520px] min-w-0 card overflow-hidden">
        <h2 class="m-0 px-5 py-4 text-lg font-bold">Points history</h2>
        <div class="table-wrap">
            <table class="table min-w-[560px]">
                <thead><tr><th>Date</th><th>Type</th><th>Details</th><th class="text-right">Points</th><th class="text-right">Balance</th></tr></thead>
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
                    <tr><td colspan="5" class="text-center text-muted py-10">No points yet — every successful order earns points.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $history->links() }}</div>
    </section>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">
        <form method="POST" action="{{ route('account.points.redeem') }}" class="card p-5 flex flex-col gap-3.5" data-redeem data-rate="{{ $rules['redeem_rate'] }}">
            @csrf
            <h2 class="m-0 text-lg font-bold flex items-center gap-2"><x-icon name="star" class="text-point" />Redeem points</h2>
            <p class="m-0 text-sm text-muted">1 point = {{ money($rules['redeem_rate']) }}. Minimum {{ number_format($rules['redeem_min']) }} points.</p>
            @if ($rules['redeem_rate'] > 0 && $user->points >= $rules['redeem_min'])
                <div>
                    <label for="points" class="label">Points to redeem</label>
                    <input id="points" name="points" type="number" min="{{ $rules['redeem_min'] }}" max="{{ $user->points }}" step="1" value="{{ old('points', $user->points) }}" class="input" required>
                    <x-field-error name="points" />
                </div>
                <div class="flex justify-between text-sm p-3 rounded-[10px] bg-point-soft text-point font-semibold"><span>You get</span><span data-redeem-value>{{ money(old('points', $user->points) * $rules['redeem_rate']) }}</span></div>
                <button class="btn btn-primary">Convert to wallet credit</button>
            @else
                <div class="rounded-[10px] px-3 py-2.5 bg-surface-2 text-sm text-muted">
                    You need {{ number_format(max(0, $rules['redeem_min'] - $user->points)) }} more points to redeem.
                </div>
            @endif
        </form>

        <section class="card p-5 flex flex-col gap-3">
            <h2 class="m-0 text-lg font-bold">Invite friends</h2>
            <p class="m-0 text-sm text-muted">You get <strong class="text-point">{{ number_format($rules['referral_bonus']) }} points</strong> when a friend you invite completes their first order.</p>
            @php $refLink = route('register', ['ref' => $user->referral_code]); @endphp
            <label for="reflink" class="sr-only">Your referral link</label>
            <input id="reflink" type="text" readonly value="{{ $refLink }}" class="input h-11 font-mono text-sm">
            <div class="flex gap-2">
                <button type="button" class="btn flex-1" data-copy="{{ $refLink }}">Copy link</button>
                <button type="button" class="btn flex-1" data-copy="{{ $user->referral_code }}">Copy code: {{ $user->referral_code }}</button>
            </div>
            @if ($referrals->isNotEmpty())
                <ul class="m-0 p-0 list-none flex flex-col gap-1.5 mt-1">
                    @foreach ($referrals as $r)
                        <li class="flex justify-between text-sm px-3 py-2 rounded-lg bg-surface-2">
                            <span>{{ $r->username }}</span>
                            @if ($r->success_orders > 0)
                                <span class="text-ok font-semibold">ordered ✓</span>
                            @else
                                <span class="text-muted">joined {{ $r->created_at->format('d M') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card p-5 flex flex-col gap-2 text-sm text-muted leading-relaxed">
            <h2 class="m-0 text-lg font-bold text-ink">How points work</h2>
            <span>Each successful order earns the points shown on the service (usually 1 point per {{ number_format($rules['per_ks']) }} Ks).</span>
            <span>Rejected or cancelled orders earn nothing.</span>
            @if ($nextGroup) <span>Earn enough points and you move up to cheaper group prices automatically.</span> @endif
        </section>
    </aside>
</div>
@endsection
