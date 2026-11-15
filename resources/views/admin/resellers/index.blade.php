@extends('layouts.admin', ['title' => 'Resellers', 'subtitle' => 'Reseller program, white-label shops and commission'])

@section('content')
@php $base = setting('reseller.base_domain') ?: parse_url(config('app.url'), PHP_URL_HOST); @endphp
<div class="flex flex-wrap gap-2 items-center">
    @foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'suspended' => 'Suspended', 'rejected' => 'Rejected'] as $k => $l)
        <a href="{{ route('admin.resellers.index', array_filter(['status' => $k])) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => ($status ?? '') === $k])>{{ $l }}{{ $k ? ' ('.($counts[$k] ?? 0).')' : '' }}</a>
    @endforeach
    <a href="{{ route('admin.withdrawals.index') }}" class="btn h-9 text-[13px] ml-auto">Withdrawals</a>
</div>

<section class="card table-wrap">
    <table class="table min-w-[1000px]">
        <thead><tr><th>Reseller</th><th>Customers</th><th class="text-right">This month (site / their profit)</th><th>Shop address</th><th>Status &amp; actions</th></tr></thead>
        <tbody>
        @forelse ($profiles as $p)
            @php $s = $stats[$p->user_id] ?? null; @endphp
            <tr class="align-top">
                <td>
                    <div class="flex items-center gap-2">
                        @if ($p->logoUrl()) <img src="{{ $p->logoUrl() }}" alt="" class="h-8 w-8 object-contain rounded bg-surface-2"> @endif
                        <div><span class="font-semibold">{{ $p->shop_name }}</span><div class="text-xs text-muted"><a href="{{ route('admin.users.show', $p->user_id) }}">{{ $p->user?->username }}</a> · {{ money($p->user?->balance) }}</div></div>
                    </div>
                    @if ($p->application_note) <div class="text-xs text-muted mt-1 max-w-xs">“{{ \Illuminate\Support\Str::limit($p->application_note, 120) }}”</div> @endif
                    <div class="text-xs text-muted mt-1">{{ $p->contact_phone }} {{ $p->contact_telegram }}</div>
                </td>
                <td>{{ $customers[$p->user_id] ?? 0 }}</td>
                <td class="text-right whitespace-nowrap">{{ money($s->site ?? 0) }} <span class="text-muted">/</span> <span class="text-ok">{{ money($s->profit ?? 0) }}</span><div class="text-xs text-muted">{{ $s->n ?? 0 }} orders</div></td>
                <td class="text-[13px]">
                    @if ($url = $p->storeUrl()) <a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a> @else <span class="text-muted">—</span> @endif
                </td>
                <td>
                    <form method="POST" action="{{ route('admin.resellers.update', $p) }}" class="grid gap-2 grid-cols-2 min-w-[360px]">
                        @csrf @method('PUT')
                        <select name="status" class="input h-9 text-sm" aria-label="Status">@foreach (['pending', 'approved', 'suspended', 'rejected'] as $st)<option value="{{ $st }}" @selected($p->status === $st)>{{ ucfirst($st) }}</option>@endforeach</select>
                        <select name="group_id" class="input h-9 text-sm" aria-label="Customer group"><option value="">Keep price group</option>@foreach ($groups as $g)<option value="{{ $g->id }}">Group: {{ $g->name }}</option>@endforeach</select>
                        <input name="subdomain" value="{{ $p->subdomain }}" class="input h-9 text-sm" placeholder="subdomain" aria-label="Subdomain">
                        <input name="custom_domain" value="{{ $p->custom_domain }}" class="input h-9 text-sm" placeholder="custom domain" aria-label="Custom domain">
                        <input name="admin_note" value="{{ $p->admin_note }}" class="input h-9 text-sm col-span-2" placeholder="Note (shown to the applicant if rejected)" aria-label="Note">
                        <button class="btn h-9 text-xs col-span-2">Save</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-10">No resellers yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $profiles->links() }}

<form method="POST" action="{{ route('admin.resellers.settings') }}" class="card p-6 flex flex-col gap-4">
    @csrf @method('PUT')
    <h2 class="m-0 text-lg font-bold">Program settings</h2>
    <div class="grid gap-4 md:grid-cols-2">
        <fieldset class="m-0 rounded-xl border border-line p-4 flex flex-col gap-3">
            <legend class="px-1 font-bold">Resellers</legend>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reseller_enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('reseller.enabled', '1') === '1')>Customers can apply to become resellers</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reseller_auto_approve" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('reseller.auto_approve') === '1')>Approve applications automatically</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reseller_whitelabel" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('reseller.whitelabel', '1') === '1')>White-label shops (own subdomain, logo, colours)</label>
            <div class="grid grid-cols-2 gap-3">
                <div><label for="dm" class="label text-xs">Default markup for new resellers (%)</label><input id="dm" name="default_markup" type="number" min="0" step="0.1" value="{{ setting('reseller.default_markup', 10) }}" class="input h-10"></div>
                <div><label for="rr" class="label text-xs">Round reseller prices up to</label><select id="rr" name="rounding" class="input h-10">@foreach ([1, 10, 50, 100, 500] as $r)<option value="{{ $r }}" @selected((int) setting('reseller.rounding', 10) === $r)>{{ $r === 1 ? 'No rounding' : $r.' Ks' }}</option>@endforeach</select></div>
            </div>
            <div><label for="bd" class="label text-xs">Base domain for shop addresses</label><input id="bd" name="base_domain" value="{{ setting('reseller.base_domain') }}" class="input h-10" placeholder="{{ parse_url(config('app.url'), PHP_URL_HOST) }}">
                <p class="m-0 mt-1 text-xs text-muted">Shops open at <code>name.{{ $base }}</code>. Needs a wildcard DNS record <code>*.{{ $base }}</code> → this server (cPanel: Domains › Create “*.{{ $base }}” pointing to the same folder). Custom domains: the reseller points their domain here and you add it as an alias/parked domain.</p></div>
        </fieldset>
        <fieldset class="m-0 rounded-xl border border-line p-4 flex flex-col gap-3">
            <legend class="px-1 font-bold">Affiliate commission</legend>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="affiliate_enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('affiliate.enabled') === '1')>Pay commission to people who refer customers</label>
            <div class="grid grid-cols-2 gap-3">
                <div><label for="ap" class="label text-xs">Commission (%)</label><input id="ap" name="percent" type="number" min="0" max="50" step="0.1" value="{{ setting('affiliate.percent', 0) }}" class="input h-10"></div>
                <div><label for="ab" class="label text-xs">Of</label><select id="ab" name="basis" class="input h-10"><option value="profit" @selected(setting('affiliate.basis', 'profit') === 'profit')>Our profit on the order</option><option value="sale" @selected(setting('affiliate.basis') === 'sale')>The order price</option></select></div>
                <div><label for="am" class="label text-xs">For how long (months, 0 = forever)</label><input id="am" name="months" type="number" min="0" value="{{ setting('affiliate.months', 0) }}" class="input h-10"></div>
                <div><label for="aw" class="label text-xs">Minimum withdrawal (Ks)</label><input id="aw" name="withdraw_min" type="number" min="0" value="{{ setting('affiliate.withdraw_min', 10000) }}" class="input h-10"></div>
            </div>
            <div><label for="awm" class="label text-xs">Payout methods (comma separated)</label><input id="awm" name="withdraw_methods" value="{{ setting('affiliate.withdraw_methods', 'KBZPay,WavePay,AYA Pay,CB Pay,Bank transfer') }}" class="input h-10"></div>
            <p class="m-0 text-xs text-muted">Reseller profit is withdrawable too. Top-ups are not.</p>
        </fieldset>
    </div>
    <button class="btn btn-primary self-start">Save settings</button>
</form>
@endsection
