@extends('layouts.admin', ['title' => 'Points', 'subtitle' => 'Earn rules, redeem rate, referral bonus and group upgrades'])

@section('content')
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Points held by users</span><span class="font-display text-[28px] font-bold text-point">{{ number_format($totals['outstanding']) }}</span><span class="text-[13px] text-muted">worth {{ money($totals['outstanding'] * $rules['redeem_rate']) }} if all redeemed</span></div>
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Earned this month</span><span class="font-display text-[28px] font-bold">{{ number_format($totals['earned_month']) }}</span></div>
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Redeemed this month</span><span class="font-display text-[28px] font-bold">{{ number_format($totals['redeemed_month']) }}</span><span class="text-[13px] text-muted">= {{ money($totals['redeemed_month'] * $rules['redeem_rate']) }} credit</span></div>
</div>

<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-5">
        <section class="card overflow-hidden">
            <div class="flex flex-wrap gap-2 items-center px-5 py-4">
                <h2 class="m-0 text-lg font-bold flex-1">Transactions</h2>
                <form method="GET" class="flex gap-2">
                    <label for="type" class="sr-only">Type</label>
                    <select id="type" name="type" class="input h-9 w-auto text-sm" onchange="this.form.submit()">
                        <option value="">All types</option>
                        @foreach ($types as $k => $l) <option value="{{ $k }}" @selected($type === $k)>{{ $l }}</option> @endforeach
                    </select>
                    <label for="q" class="sr-only">Username</label>
                    <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Username…" class="input h-9 w-40 text-sm">
                    <button class="btn h-9 text-xs">Filter</button>
                </form>
            </div>
            <div class="table-wrap">
                <table class="table min-w-[640px]">
                    <thead><tr><th>Date</th><th>User</th><th>Type</th><th>Details</th><th class="text-right">Points</th><th class="text-right">After</th></tr></thead>
                    <tbody>
                    @forelse ($transactions as $t)
                        <tr>
                            <td class="whitespace-nowrap text-muted">{{ $t->created_at->format('d M H:i') }}</td>
                            <td class="font-semibold">{{ $t->user?->username }}</td>
                            <td><span @class(['badge', 'badge-success' => $t->points > 0, 'badge-rejected' => $t->points < 0])>{{ $types[$t->type] ?? $t->type }}</span></td>
                            <td>{{ $t->description }}</td>
                            <td @class(['text-right font-semibold', 'text-ok' => $t->points > 0, 'text-danger' => $t->points < 0])>{{ $t->points > 0 ? '+' : '−' }}{{ number_format(abs($t->points)) }}</td>
                            <td class="text-right">{{ number_format($t->points_after) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-10">No point transactions yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $transactions->links() }}</div>
        </section>
    </div>

    <aside class="flex-[1_1_360px] min-w-0 flex flex-col gap-5">
        <form method="POST" action="{{ route('admin.points.rules') }}" class="card p-5 flex flex-col gap-4">
            @csrf @method('PUT')
            <h2 class="m-0 text-lg font-bold">Rules</h2>
            <div>
                <label for="per_ks" class="label">Earn 1 point per … Ks spent</label>
                <input id="per_ks" name="per_ks" type="number" min="0" value="{{ old('per_ks', $rules['per_ks']) }}" class="input h-11">
                <p class="m-0 mt-1 text-xs text-muted">Used when a service has no own points value. 0 = no points.</p>
                <x-field-error name="per_ks" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="redeem_rate" class="label">1 point = … Ks</label>
                    <input id="redeem_rate" name="redeem_rate" type="number" min="0" step="0.01" value="{{ old('redeem_rate', $rules['redeem_rate']) }}" class="input h-11">
                    <x-field-error name="redeem_rate" />
                </div>
                <div>
                    <label for="redeem_min" class="label">Min redeem</label>
                    <input id="redeem_min" name="redeem_min" type="number" min="1" value="{{ old('redeem_min', $rules['redeem_min']) }}" class="input h-11">
                    <x-field-error name="redeem_min" />
                </div>
            </div>
            <div>
                <label for="referral_bonus" class="label">Referral bonus (points)</label>
                <input id="referral_bonus" name="referral_bonus" type="number" min="0" value="{{ old('referral_bonus', $rules['referral_bonus']) }}" class="input h-11">
                <p class="m-0 mt-1 text-xs text-muted">Given to the referrer when their friend's first order succeeds. 0 = off.</p>
            </div>
            <fieldset class="m-0 p-0 border-0 flex flex-col gap-2">
                <legend class="label">Auto-upgrade group at lifetime points</legend>
                @foreach ($groups as $g)
                    <div class="grid grid-cols-[1fr_9rem] gap-2.5 items-center text-sm">
                        <label for="th{{ $g->id }}">{{ $g->name }} @if ($g->is_default) <span class="text-muted">(default)</span> @endif</label>
                        <input id="th{{ $g->id }}" name="thresholds[{{ $g->id }}]" type="number" min="1" value="{{ old('thresholds.'.$g->id, $g->min_points_for_auto_upgrade) }}" class="input h-10" placeholder="{{ $g->is_default ? '—' : 'off' }}" @disabled($g->is_default)>
                    </div>
                @endforeach
                <p class="m-0 text-xs text-muted">Empty = no automatic upgrade. Users are only moved up (by group sort order), never down.</p>
            </fieldset>
            <button class="btn btn-primary">Save rules</button>
        </form>

        <form method="POST" action="{{ route('admin.points.adjust') }}" class="card p-5 flex flex-col gap-3.5">
            @csrf
            <h2 class="m-0 text-lg font-bold">Adjust a user's points</h2>
            <div>
                <label for="username" class="label">Username</label>
                <input id="username" name="username" value="{{ old('username') }}" class="input h-11" required>
                <x-field-error name="username" />
            </div>
            <div class="grid grid-cols-[8rem_1fr] gap-3">
                <div>
                    <label for="direction" class="label">Action</label>
                    <select id="direction" name="direction" class="input h-11">
                        <option value="add">Add</option>
                        <option value="subtract" @selected(old('direction') === 'subtract')>Remove</option>
                    </select>
                </div>
                <div>
                    <label for="adj_points" class="label">Points</label>
                    <input id="adj_points" name="points" type="number" min="1" value="{{ old('points') }}" class="input h-11" required>
                    <x-field-error name="points" />
                </div>
            </div>
            <div>
                <label for="reason" class="label">Reason <span class="font-normal text-muted">(user sees this)</span></label>
                <input id="reason" name="reason" value="{{ old('reason') }}" class="input h-11" placeholder="e.g. Promo bonus" required>
                <x-field-error name="reason" />
            </div>
            <button class="btn">Apply</button>
        </form>
    </aside>
</div>
@endsection
