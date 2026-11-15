@extends('layouts.admin', ['title' => 'Staff & permissions', 'subtitle' => 'Owner only'])

@section('content')
<div class="flex flex-wrap gap-5 items-start">
    <section class="card flex-[999_1_560px] min-w-0 overflow-hidden">
        <h2 class="m-0 px-5 py-4 text-lg font-bold">Staff</h2>
        <div class="table-wrap">
            <table class="table min-w-[560px]">
                <thead><tr><th>Name</th><th>Role</th><th>2FA</th><th></th></tr></thead>
                <tbody>
                @foreach ($staff as $s)
                    <tr>
                        <td><span class="font-semibold">{{ $s->name }}</span><div class="text-xs text-muted">{{ $s->username }}</div></td>
                        <td>
                            @if ($s->is(auth()->user()))
                                <span class="badge badge-info">{{ \App\Support\Permissions::ROLES[$s->staffRole()] }}</span> <span class="text-xs text-muted">(you)</span>
                            @else
                                <form method="POST" action="{{ route('admin.staff.update', $s) }}" class="flex gap-2">
                                    @csrf @method('PUT')
                                    <label for="role_{{ $s->id }}" class="sr-only">Role</label>
                                    <select id="role_{{ $s->id }}" name="staff_role" class="input h-9 w-auto text-sm">
                                        @foreach (\App\Support\Permissions::ROLES as $k => $l) <option value="{{ $k }}" @selected($s->staffRole() === $k)>{{ $l }}</option> @endforeach
                                    </select>
                                    <button class="btn h-9 text-xs">Save</button>
                                </form>
                            @endif
                        </td>
                        <td>@if ($s->hasTwoFactor()) <span class="badge badge-success">On</span> @else <span class="badge badge-rejected">Off</span> @endif</td>
                        <td class="text-right">
                            @unless ($s->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.staff.destroy', $s) }}" onsubmit="return confirm('Remove {{ $s->username }} from staff?')">@csrf @method('DELETE')<button class="btn btn-danger h-9 text-xs">Remove</button></form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">
        <form method="POST" action="{{ route('admin.staff.store') }}" class="card p-5 flex flex-col gap-3">
            @csrf
            <h2 class="m-0 text-lg font-bold">Add staff</h2>
            <div><label for="login" class="label text-xs">Username or email (existing account)</label><input id="login" name="login" value="{{ old('login') }}" class="input h-10" required></div>
            <div>
                <label for="staff_role" class="label text-xs">Role</label>
                <select id="staff_role" name="staff_role" class="input h-10">
                    @foreach (\App\Support\Permissions::ROLES as $k => $l) <option value="{{ $k }}" @selected(old('staff_role', 'support') === $k)>{{ $l }}</option> @endforeach
                </select>
            </div>
            <ul class="m-0 pl-4 text-xs text-muted flex flex-col gap-1">
                @foreach (\App\Support\Permissions::ROLE_HINTS as $k => $h) <li><strong>{{ \App\Support\Permissions::ROLES[$k] }}:</strong> {{ $h }}</li> @endforeach
            </ul>
            <p class="m-0 text-xs text-muted">They must have Google Authenticator on.</p>
            <button class="btn btn-primary">Add</button>
        </form>

        <form id="threshold" method="POST" action="{{ route('admin.staff.threshold') }}" class="card p-5 flex flex-col gap-3">
            @csrf @method('PUT')
            <h2 class="m-0 text-lg font-bold">Second approval for wallet changes</h2>
            <div><label for="th" class="label text-xs">Manual add/remove of this amount (Ks) or more needs another staff member. 0 = off.</label>
                <input id="th" name="threshold" type="number" min="0" step="1" value="{{ (int) $threshold }}" class="input h-10"></div>
            <button class="btn self-start">Save</button>
        </form>
    </aside>
</div>

<form method="POST" action="{{ route('admin.staff.permissions') }}" class="card overflow-hidden">
    @csrf @method('PUT')
    <div class="px-5 py-4 flex flex-wrap justify-between items-center gap-2">
        <div>
            <h2 class="m-0 text-lg font-bold">What each role can do</h2>
            <p class="m-0 text-[13px] text-muted">Blocked pages are closed by URL too, not only hidden from the menu. Owner always has everything.</p>
        </div>
        <button class="btn btn-primary">Save permissions</button>
    </div>
    <div class="table-wrap">
        <table class="table min-w-[760px]">
            <thead><tr><th>Permission</th>@foreach (\App\Support\Permissions::ROLES as $k => $l)<th class="text-center">{{ $l }}</th>@endforeach</tr></thead>
            <tbody>
            @php $group = null; @endphp
            @foreach (\App\Support\Permissions::ALL as $perm => [$g, $label])
                @if ($g !== $group)
                    @php $group = $g; @endphp
                    <tr><td colspan="{{ count(\App\Support\Permissions::ROLES) + 1 }}" class="bg-surface-2 text-xs font-bold uppercase tracking-wide text-muted">{{ $g }}</td></tr>
                @endif
                <tr>
                    <td>{{ $label }} <code class="text-[11px] text-muted">{{ $perm }}</code></td>
                    @foreach (\App\Support\Permissions::ROLES as $role => $l)
                        <td class="text-center">
                            @if ($role === 'owner' || in_array($perm, \App\Support\Permissions::OWNER_ONLY, true))
                                <input type="checkbox" class="w-4 h-4" @checked($role === 'owner') disabled aria-label="{{ $l }}: {{ $label }}">
                            @else
                                <input type="checkbox" name="perm[{{ $role }}][{{ $perm }}]" value="1" class="w-4 h-4 accent-[var(--uh-brand)]" @checked(in_array($perm, $matrix[$role], true)) aria-label="{{ $l }}: {{ $label }}">
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</form>
@endsection
