@extends('layouts.admin', ['title' => 'Groups & Pricing', 'subtitle' => 'Price tiers. Each service can have a different price per group; empty = default price.'])

@section('content')
<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_560px] min-w-0 card table-wrap">
        <table class="table min-w-[680px]">
            <thead><tr><th>Name</th><th class="w-24">Rank</th><th>Users</th><th>Custom prices</th><th>Auto-upgrade</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach ($groups as $g)
                <tr>
                    <td colspan="2" class="p-0">
                        <form id="g{{ $g->id }}" method="POST" action="{{ route('admin.groups.update', $g) }}" class="grid grid-cols-[1fr_6rem] items-center">
                            @csrf @method('PUT')
                            <div class="px-4 py-2.5 flex items-center gap-2">
                                <label for="gn{{ $g->id }}" class="sr-only">Name</label>
                                <input id="gn{{ $g->id }}" name="name" value="{{ $g->name }}" class="input h-10">
                                @if ($g->is_default) <span class="badge badge-info text-[11px]">Default</span> @endif
                            </div>
                            <div class="px-4 py-2.5"><label for="gs{{ $g->id }}" class="sr-only">Rank</label><input id="gs{{ $g->id }}" name="sort" type="number" min="0" value="{{ $g->sort }}" class="input h-10"></div>
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
@endsection
