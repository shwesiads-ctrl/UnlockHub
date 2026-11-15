@extends('layouts.admin', ['title' => 'Users', 'subtitle' => number_format($totals['users']).' users · '.money($totals['balance']).' in wallets · '.$totals['new_week'].' new this week'])

@section('content')
<form method="GET" class="flex flex-wrap gap-2 items-center">
    <label for="q" class="sr-only">Search</label>
    <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Username, email, name, phone…" class="input h-10 w-72">
    <label for="group" class="sr-only">Group</label>
    <select id="group" name="group" class="input h-10 w-auto">
        <option value="">All groups</option>
        @foreach ($groups as $g) <option value="{{ $g->id }}" @selected((int) request('group') === $g->id)>{{ $g->name }}</option> @endforeach
    </select>
    <label for="status" class="sr-only">Status</label>
    <select id="status" name="status" class="input h-10 w-auto">
        <option value="">Any status</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
    </select>
    <label for="role" class="sr-only">Role</label>
    <select id="role" name="role" class="input h-10 w-auto">
        <option value="">Users &amp; admins</option>
        <option value="admin" @selected(request('role') === 'admin')>Admins</option>
        <option value="user" @selected(request('role') === 'user')>Users</option>
    </select>
    <label for="sort" class="sr-only">Sort</label>
    <select id="sort" name="sort" class="input h-10 w-auto">
        @foreach (['latest' => 'Newest', 'balance' => 'Highest balance', 'points' => 'Most points', 'last_login' => 'Last login'] as $k => $l)
            <option value="{{ $k }}" @selected($sort === $k)>{{ $l }}</option>
        @endforeach
    </select>
    <button class="btn h-10">Filter</button>
    <a href="{{ route('admin.groups.index') }}" class="btn h-10 ml-auto">Manage groups</a>
</form>

<section class="card table-wrap">
    <table class="table min-w-[900px]">
        <thead><tr><th>User</th><th>Group</th><th class="text-right">Balance</th><th class="text-right">Points</th><th>Orders</th><th>Last login</th><th>Status</th><th><span class="sr-only">Open</span></th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            <tr>
                <td>
                    <a href="{{ route('admin.users.show', $u) }}" class="font-semibold">{{ $u->username }}</a>
                    @if ($u->isAdmin()) <span class="badge badge-info text-[11px] ml-1">Admin</span> @endif
                    @if ($u->hasTwoFactor()) <span class="badge badge-success text-[11px] ml-1">2FA</span> @endif
                    <div class="text-xs text-muted">{{ $u->name }} · {{ $u->email }}</div>
                </td>
                <td><x-user-badges :user="$u" size="sm" :staff="true" /></td>
                <td class="text-right whitespace-nowrap font-semibold">{{ money($u->balance) }}</td>
                <td class="text-right text-point">{{ number_format($u->points) }}</td>
                <td>{{ $u->orders_count }}</td>
                <td class="text-muted whitespace-nowrap text-[13px]">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
                <td><span @class(['badge', 'badge-success' => $u->status === 'active', 'badge-rejected' => $u->status !== 'active'])>{{ ucfirst($u->status) }}</span></td>
                <td class="text-right"><a href="{{ route('admin.users.show', $u) }}" class="btn h-9 text-xs">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-10">No users match.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $users->links() }}
@endsection
