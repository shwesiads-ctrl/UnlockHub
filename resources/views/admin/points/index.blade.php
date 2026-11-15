@extends('layouts.admin', ['title' => 'Points', 'subtitle' => 'Earn rules, redeem rate, referral bonus and group upgrades'])

@section('content')
<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Points held by users</span><span class="font-display text-[28px] font-bold text-point">{{ number_format($totals['outstanding']) }}</span><span class="text-[13px] text-muted">worth {{ money($totals['outstanding'] * $rules['redeem_rate']) }} if all redeemed</span></div>
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Earned this month</span><span class="font-display text-[28px] font-bold">{{ number_format($totals['earned_month']) }}</span></div>
    <div class="card p-5 flex flex-col gap-2"><span class="text-[13px] font-semibold text-muted">Redeemed this month</span><span class="font-display text-[28px] font-bold">{{ number_format($totals['redeemed_month']) }}</span><span class="text-[13px] text-muted">= {{ money($totals['redeemed_month'] * $rules['redeem_rate']) }} credit</span></div>
</div>

<div class="flex flex-wrap gap-2 mt-5" role="tablist" aria-label="Points management">
    <button type="button" class="btn btn-primary" data-points-tab="points-activity" role="tab" aria-selected="true">Transactions</button>
    <button type="button" class="btn" data-points-tab="points-rules" role="tab" aria-selected="false">Point settings</button>
    <button type="button" class="btn" data-points-tab="points-adjust" role="tab" aria-selected="false">Adjust points</button>
</div>

<section id="points-activity" class="mt-4" role="tabpanel">
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
</section>

<section id="points-rules" class="mt-4" role="tabpanel" hidden>
    <form method="POST" action="{{ route('admin.points.rules') }}" class="card p-5 flex flex-col gap-4">
        @csrf @method('PUT')
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1"><h2 class="m-0 text-lg font-bold">Point system settings</h2><p class="m-0 mt-1 text-xs text-muted">Set one section at a time. Save applies all settings, including sections you have not opened.</p></div>
            <span class="badge {{ setting('points.enabled', '1') === '1' ? 'badge-success' : 'badge-pending' }}">{{ setting('points.enabled', '1') === '1' ? 'Point system on' : 'Point system off' }}</span>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-line pb-3" role="tablist" aria-label="Point rule sections">
            <button type="button" class="btn btn-primary" data-point-rule-tab="rule-system" role="tab" aria-selected="true">System</button>
            <button type="button" class="btn" data-point-rule-tab="rule-rates" role="tab" aria-selected="false">Earn &amp; redeem</button>
            <button type="button" class="btn" data-point-rule-tab="rule-bonuses" role="tab" aria-selected="false">Bonuses &amp; check-in</button>
            <button type="button" class="btn" data-point-rule-tab="rule-groups" role="tab" aria-selected="false">Group upgrades</button>
        </div>

        <div id="rule-system" class="point-rule-page flex flex-col gap-4">
            <fieldset class="m-0 p-0 border-0 flex flex-col gap-2">
                <legend class="label">Service payment system — choose one</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-xl border border-line p-3 flex items-start gap-2.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                        <input type="radio" name="price_currency" value="ks" class="mt-1 accent-[var(--uh-brand)]" @checked(old('price_currency', setting('catalog.price_currency', 'ks')) === 'ks')>
                        <span><strong class="flex items-center gap-2">Ks system @if (old('price_currency', setting('catalog.price_currency', 'ks')) === 'ks') <span class="badge badge-success">On</span> @else <span class="badge bg-surface-2 text-muted">Off</span> @endif</strong><span class="text-xs text-muted">Orders use wallet balance in Ks.</span></span>
                    </label>
                    <label class="cursor-pointer rounded-xl border border-line p-3 flex items-start gap-2.5 has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                        <input type="radio" name="price_currency" value="points" class="mt-1 accent-[var(--uh-brand)]" @checked(old('price_currency', setting('catalog.price_currency', 'ks')) === 'points')>
                        <span><strong class="flex items-center gap-2">Points system @if (old('price_currency', setting('catalog.price_currency', 'ks')) === 'points') <span class="badge badge-success">On</span> @else <span class="badge bg-surface-2 text-muted">Off</span> @endif</strong><span class="text-xs text-muted">Orders use points; Ks service prices convert at the rate below.</span></span>
                    </label>
                </div>
                <p class="m-0 text-xs text-muted">Only one service payment system is active at a time. Switching updates service prices; existing orders keep their original unit.</p>
            </fieldset>

            <label class="flex items-center justify-between gap-3 p-3 rounded-xl {{ setting('points.enabled', '1') === '1' ? 'bg-ok-soft' : 'bg-danger-soft' }}">
                <span class="flex flex-col"><span class="font-bold">Point system</span><span class="text-xs text-muted">Off hides points and disables earning or redemption.</span></span>
                <input type="checkbox" name="enabled" value="1" class="w-6 h-6 accent-[var(--uh-brand)]" @checked(setting('points.enabled', '1') === '1')>
            </label>
            <fieldset class="m-0 p-0 border-0">
                <legend class="label mb-2">Point features</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($features as $key => $label)
                        <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg bg-surface-2 text-sm"><span>{{ $label }}</span><input type="checkbox" name="on_{{ $key }}" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting("points.on.{$key}", '1') === '1')></label>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div id="rule-rates" class="point-rule-page flex-col gap-4" hidden>
            <div><h3 class="m-0 text-base font-bold">Earning &amp; redemption</h3><p class="m-0 mt-1 text-xs text-muted">Set the default earn rate and the value users receive when redeeming.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="per_ks" class="label">Earn 1 point per … Ks spent</label><input id="per_ks" name="per_ks" type="number" min="0" value="{{ old('per_ks', $rules['per_ks']) }}" class="input h-11"><p class="m-0 mt-1 text-xs text-muted">Used if a service has no custom points value. 0 = off.</p><x-field-error name="per_ks" /></div>
                <div><label for="redeem_rate" class="label">1 point = … Ks</label><input id="redeem_rate" name="redeem_rate" type="number" min="0" step="0.01" value="{{ old('redeem_rate', $rules['redeem_rate']) }}" class="input h-11"><x-field-error name="redeem_rate" /></div>
                <div><label for="redeem_min" class="label">Minimum redeem points</label><input id="redeem_min" name="redeem_min" type="number" min="1" value="{{ old('redeem_min', $rules['redeem_min']) }}" class="input h-11"><x-field-error name="redeem_min" /></div>
                <div><label for="convert_rate" class="label">Ks per 1 point when converting wallet</label><input id="convert_rate" name="convert_rate" type="number" min="1" value="{{ old('convert_rate', $rules['convert_rate']) }}" class="input h-11"><p class="m-0 mt-1 text-xs text-muted">The wallet conversion switch controls whether users can use this rate.</p><x-field-error name="convert_rate" /></div>
            </div>
        </div>

        <div id="rule-bonuses" class="point-rule-page flex-col gap-4" hidden>
            <div><h3 class="m-0 text-base font-bold">Bonuses &amp; check-in</h3><p class="m-0 mt-1 text-xs text-muted">Configure referral, account, top-up and daily check-in rewards. Set a value to 0 to turn that bonus off.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label for="referral_bonus" class="label">Referral bonus (points)</label><input id="referral_bonus" name="referral_bonus" type="number" min="0" value="{{ old('referral_bonus', $rules['referral_bonus']) }}" class="input h-11"><p class="m-0 mt-1 text-xs text-muted">Given when a referred user's first order succeeds.</p></div>
                <div><label for="signup_bonus" class="label">Welcome bonus</label><input id="signup_bonus" name="signup_bonus" type="number" min="0" value="{{ old('signup_bonus', $rules['signup_bonus']) }}" class="input h-11"></div>
                <div><label for="profile_bonus" class="label">Profile complete bonus</label><input id="profile_bonus" name="profile_bonus" type="number" min="0" value="{{ old('profile_bonus', $rules['profile_bonus']) }}" class="input h-11"></div>
                <div><label for="topup_per_ks" class="label">Top-up: 1 point per … Ks</label><input id="topup_per_ks" name="topup_per_ks" type="number" min="0" value="{{ old('topup_per_ks', $rules['topup_per_ks']) }}" class="input h-11"><p class="m-0 mt-1 text-xs text-muted">For example, 10,000 = 5 points for a 50,000 Ks top-up.</p></div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label for="checkin_points" class="label">Daily check-in</label><input id="checkin_points" name="checkin_points" type="number" min="0" value="{{ old('checkin_points', $rules['checkin_points']) }}" class="input h-11"></div>
                <div><label for="checkin_streak_bonus" class="label">Extra per streak day</label><input id="checkin_streak_bonus" name="checkin_streak_bonus" type="number" min="0" value="{{ old('checkin_streak_bonus', $rules['checkin_streak_bonus']) }}" class="input h-11"></div>
                <div><label for="checkin_streak_max" class="label">Streak limit (days)</label><input id="checkin_streak_max" name="checkin_streak_max" type="number" min="1" max="60" value="{{ old('checkin_streak_max', $rules['checkin_streak_max']) }}" class="input h-11"></div>
            </div>
            <p class="m-0 text-xs text-muted">Day one gives the daily check-in amount. Each consecutive day adds the streak bonus up to the limit.</p>
        </div>

        <div id="rule-groups" class="point-rule-page flex-col gap-4" hidden>
            <div><h3 class="m-0 text-base font-bold">Automatic group upgrades</h3><p class="m-0 mt-1 text-xs text-muted">Set lifetime point thresholds. Users move up by group order and are never downgraded automatically.</p></div>
            <fieldset class="m-0 p-0 border-0 flex flex-col gap-2">
                <legend class="label">Upgrade a user at this many lifetime points</legend>
                @foreach ($groups as $g)
                    <div class="grid grid-cols-[1fr_9rem] gap-2.5 items-center text-sm"><label for="th{{ $g->id }}">{{ $g->name }} @if ($g->is_default) <span class="text-muted">(default)</span> @endif</label><input id="th{{ $g->id }}" name="thresholds[{{ $g->id }}]" type="number" min="1" value="{{ old('thresholds.'.$g->id, $g->min_points_for_auto_upgrade) }}" class="input h-10" placeholder="{{ $g->is_default ? '—' : 'off' }}" @disabled($g->is_default)></div>
                @endforeach
                <p class="m-0 text-xs text-muted">Leave a threshold empty to disable automatic upgrades for that group.</p>
            </fieldset>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
            <button type="button" class="btn" data-rule-previous>‹ Previous</button>
            <span class="text-xs text-muted" data-rule-count aria-live="polite">Section 1 of 4</span>
            <button type="button" class="btn" data-rule-next>Next ›</button>
            <button class="btn btn-primary ml-auto">Save all point settings</button>
        </div>
    </form>
</section>

<section id="points-adjust" class="mt-4" role="tabpanel" hidden>
    <form method="POST" action="{{ route('admin.points.adjust') }}" class="card p-5 flex flex-col gap-3.5 max-w-2xl">
        @csrf
        <h2 class="m-0 text-lg font-bold">Adjust a user's points</h2>
        <div><label for="username" class="label">Username</label><input id="username" name="username" value="{{ old('username') }}" class="input h-11" required><x-field-error name="username" /></div>
        <div class="grid grid-cols-[8rem_1fr] gap-3">
            <div><label for="direction" class="label">Action</label><select id="direction" name="direction" class="input h-11"><option value="add">Add</option><option value="subtract" @selected(old('direction') === 'subtract')>Remove</option></select></div>
            <div><label for="adj_points" class="label">Points</label><input id="adj_points" name="points" type="number" min="1" value="{{ old('points') }}" class="input h-11" required><x-field-error name="points" /></div>
        </div>
        <div><label for="reason" class="label">Reason <span class="font-normal text-muted">(user sees this)</span></label><input id="reason" name="reason" value="{{ old('reason') }}" class="input h-11" placeholder="e.g. Promo bonus" required><x-field-error name="reason" /></div>
        <button class="btn btn-primary self-start">Apply adjustment</button>
    </form>
</section>

<script>
(function () {
    'use strict';
    var mainTabs = Array.from(document.querySelectorAll('[data-points-tab]'));
    var mainPanels = mainTabs.map(function (tab) { return document.getElementById(tab.dataset.pointsTab); }).filter(Boolean);
    var ruleTabs = Array.from(document.querySelectorAll('[data-point-rule-tab]'));
    var rulePages = ruleTabs.map(function (tab) { return document.getElementById(tab.dataset.pointRuleTab); }).filter(Boolean);

    function activateMain(id) {
        var selected = mainPanels.some(function (panel) { return panel.id === id; }) ? id : 'points-rules';
        mainPanels.forEach(function (panel) { panel.hidden = panel.id !== selected; });
        mainTabs.forEach(function (tab) {
            var active = tab.dataset.pointsTab === selected;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.classList.toggle('btn-primary', active);
        });
        try { window.sessionStorage.setItem('admin-points-tab', selected); } catch (e) {}
    }

    function activateRule(id) {
        var selected = rulePages.some(function (page) { return page.id === id; }) ? id : rulePages[0].id;
        rulePages.forEach(function (page) {
            page.hidden = page.id !== selected;
            page.style.display = page.hidden ? 'none' : 'flex';
        });
        ruleTabs.forEach(function (tab) {
            var active = tab.dataset.pointRuleTab === selected;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.classList.toggle('btn-primary', active);
        });
        var index = rulePages.findIndex(function (page) { return page.id === selected; });
        var count = document.querySelector('[data-rule-count]');
        if (count) count.textContent = 'Section ' + (index + 1) + ' of ' + rulePages.length;
        try { window.sessionStorage.setItem('admin-points-rule', selected); } catch (e) {}
    }

    mainTabs.forEach(function (tab) { tab.addEventListener('click', function () { activateMain(tab.dataset.pointsTab); }); });
    ruleTabs.forEach(function (tab) { tab.addEventListener('click', function () { activateRule(tab.dataset.pointRuleTab); }); });
    var previous = document.querySelector('[data-rule-previous]');
    var next = document.querySelector('[data-rule-next]');
    function stepRule(direction) {
        var activeIndex = rulePages.findIndex(function (page) { return !page.hidden; });
        activateRule(rulePages[(activeIndex + direction + rulePages.length) % rulePages.length].id);
    }
    if (previous) previous.addEventListener('click', function () { stepRule(-1); });
    if (next) next.addEventListener('click', function () { stepRule(1); });

    var mainInitial = 'points-rules';
    var ruleInitial = 'rule-system';
    try {
        mainInitial = window.sessionStorage.getItem('admin-points-tab') || mainInitial;
        ruleInitial = window.sessionStorage.getItem('admin-points-rule') || ruleInitial;
    } catch (e) {}
    activateMain(mainInitial);
    activateRule(ruleInitial);
})();
</script>
@endsection
