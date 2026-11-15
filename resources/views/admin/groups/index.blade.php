@extends('layouts.admin', ['title' => 'Groups & Pricing', 'subtitle' => 'Price tiers. Each service can have a different price per group; empty = default price.'])

@section('content')
<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_560px] min-w-0 card table-wrap">
        <table class="table min-w-[680px]">
            <thead><tr><th>Name</th><th class="w-24">Rank</th><th>Pricing rule (auto services)</th><th>Users</th><th>Custom prices</th><th>Auto-upgrade</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach ($groups as $g)
                <tr>
                    <td colspan="3" class="p-0">
                        <form id="g{{ $g->id }}" method="POST" action="{{ route('admin.groups.update', $g) }}" class="grid grid-cols-[1fr_6rem_17rem] items-center">
                            @csrf @method('PUT')
                            <div class="px-4 py-2.5 flex items-center gap-2">
                                <label for="gn{{ $g->id }}" class="sr-only">Name</label>
                                <input id="gn{{ $g->id }}" name="name" value="{{ $g->name }}" class="input h-10">
                                @if ($g->is_default) <span class="badge badge-info text-[11px]">Default</span> @endif
                            </div>
                            <div class="px-4 py-2.5"><label for="gs{{ $g->id }}" class="sr-only">Rank</label><input id="gs{{ $g->id }}" name="sort" type="number" min="0" value="{{ $g->sort }}" class="input h-10"></div>
                            <div class="px-4 py-2.5 flex gap-1.5 items-center text-xs">
                                <label for="gmv{{ $g->id }}" class="sr-only">Markup</label>
                                <input id="gmv{{ $g->id }}" name="markup_value" type="number" min="0" step="0.01" value="{{ (float) $g->markup_value }}" class="input h-10 w-20">
                                <label for="gmt{{ $g->id }}" class="sr-only">Markup type</label>
                                <select id="gmt{{ $g->id }}" name="markup_type" class="input h-10 w-16 px-1"><option value="percent" @selected($g->markup_type === 'percent')>%</option><option value="fixed" @selected($g->markup_type === 'fixed')>Ks</option></select>
                                <label for="gmp{{ $g->id }}" class="whitespace-nowrap text-muted">min</label>
                                <input id="gmp{{ $g->id }}" name="min_profit" type="number" min="0" step="1" value="{{ (float) $g->min_profit }}" class="input h-10 w-20" title="Minimum profit (Ks)">
                            </div>
                        </form>
                    </td>
                    <td><a href="{{ route('admin.users.index', ['group' => $g->id]) }}">{{ $g->users_count }}</a></td>
                    <td>{{ $g->prices_count }}</td>
                    <td class="text-[13px] text-muted">{{ $g->min_points_for_auto_upgrade ? number_format($g->min_points_for_auto_upgrade).' pts' : '—' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <button form="g{{ $g->id }}" class="btn h-9 text-xs">Save</button>
                        @unless ($g->is_default)
                            <form method="POST" action="{{ route('admin.groups.default', $g) }}" class="inline">@csrf<button class="btn h-9 text-xs">Make default</button></form>
                            <form method="POST" action="{{ route('admin.groups.destroy', $g) }}" class="inline" onsubmit="return confirm('Delete {{ $g->name }}? Its users move to the default group and its custom prices are removed.')">@csrf @method('DELETE')<button class="btn btn-danger h-9 text-xs">Delete</button></form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">
        <form method="POST" action="{{ route('admin.groups.pricing-settings') }}" class="card p-5 flex flex-col gap-3">
            @csrf @method('PUT')
            <h2 class="m-0 text-lg font-bold">Pricing &amp; profit protection</h2>
            <p class="m-0 text-xs text-muted">Auto-priced services: price = cost + group markup (at least the group's min profit), rounded up. Manual services keep their own prices.</p>
            <div class="grid grid-cols-2 gap-3">
                <div><label for="rounding" class="label text-xs">Round up to</label>
                    <select id="rounding" name="rounding" class="input h-10">@foreach ([1, 10, 50, 100, 500, 1000] as $r)<option value="{{ $r }}" @selected((int) setting('pricing.rounding', 100) === $r)>{{ $r === 1 ? 'No rounding' : number_format($r).' Ks' }}</option>@endforeach</select></div>
                <div><label for="global_min_profit" class="label text-xs">Warn if profit below (Ks)</label>
                    <input id="global_min_profit" name="global_min_profit" type="number" min="0" value="{{ (float) setting('pricing.global_min_profit', 0) }}" class="input h-10"></div>
                <div><label for="loss_action" class="label text-xs">If cost ≥ a selling price</label>
                    <select id="loss_action" name="loss_action" class="input h-10"><option value="pause" @selected(setting('pricing.loss_action', 'pause') === 'pause')>Pause new orders</option><option value="warn" @selected(setting('pricing.loss_action', 'pause') === 'warn')>Warn only</option></select></div>
                <div><label for="approval_percent" class="label text-xs">Approve auto changes ≥ (%)</label>
                    <input id="approval_percent" name="approval_percent" type="number" min="0" step="0.1" value="{{ (float) setting('pricing.approval_percent', 20) }}" class="input h-10"></div>
            </div>
            <p class="m-0 text-xs text-muted">0% = apply every automatic change straight away.</p>
            <button class="btn btn-primary self-start">Save</button>
        </form>
        <form method="POST" action="{{ route('admin.groups.reprice') }}" onsubmit="return confirm('Recalculate the prices of all auto-priced services now?')" class="card p-5 flex flex-col gap-2">
            @csrf
            <span class="text-sm">After changing rules, apply them to every auto-priced service (orders already placed keep their price).</span>
            <button class="btn self-start">Reprice auto services</button>
        </form>
        <form method="POST" action="{{ route('admin.groups.store') }}" class="card p-5 flex flex-col gap-3.5">
            @csrf
            <h2 class="m-0 text-lg font-bold">Add group</h2>
            <div><label for="name" class="label">Name</label><input id="name" name="name" value="{{ old('name') }}" class="input h-11" placeholder="e.g. Gold Reseller" required><x-field-error name="name" /></div>
            <div><label for="sort" class="label">Rank <span class="font-normal text-muted">(higher = better tier)</span></label><input id="sort" name="sort" type="number" min="0" value="{{ old('sort') }}" class="input h-11"></div>
            <button class="btn btn-primary">Add</button>
        </form>
        <section class="card p-5 flex flex-col gap-2 text-sm text-muted leading-relaxed">
            <h2 class="m-0 text-lg font-bold text-ink">Tips</h2>
            <span>New users join the <strong class="text-ink">default</strong> group.</span>
            <span>Move a user between groups in <a href="{{ route('admin.users.index') }}">Users</a>, or let points do it: thresholds are in <a href="{{ route('admin.points.index') }}">Points</a>.</span>
            <span>Set prices per group inside each service (Services › Edit › Pricing).</span>
        </section>
    </aside>
</div>
{{ $groups->links() }}
@endsection
