@extends('layouts.admin', ['title' => $user->username, 'subtitle' => 'Users / '.$user->name.' · joined '.$user->created_at->format('d M Y')])

@section('content')
<a href="{{ route('admin.users.index') }}" class="text-sm self-start">← All users</a>

<div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
    <div class="bg-brand text-white rounded-2xl p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold opacity-90">Balance</span><span class="font-display text-[26px] font-bold">{{ money($user->balance) }}</span></div>
    <div class="card p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold text-muted">Points</span><span class="font-display text-[26px] font-bold text-point">{{ number_format($user->points) }}</span></div>
    <div class="card p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold text-muted">Deposited</span><span class="font-display text-[26px] font-bold">{{ money($stats['deposited']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold text-muted">Spent (success)</span><span class="font-display text-[26px] font-bold">{{ money($stats['spent']) }}</span><span class="text-xs text-ok font-semibold">profit {{ money($stats['profit']) }}</span></div>
    <div class="card p-5 flex flex-col gap-1.5"><span class="text-[13px] font-semibold text-muted">Orders</span><span class="font-display text-[26px] font-bold">{{ $stats['orders']->sum() }}</span>
        <span class="text-xs text-muted">{{ $stats['orders']['success'] ?? 0 }} ok · {{ $stats['orders']['rejected'] ?? 0 }} rejected · {{ ($stats['orders']['pending'] ?? 0) + ($stats['orders']['in_process'] ?? 0) }} open</span></div>
</div>

<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_560px] min-w-0 flex flex-col gap-5">
        <section class="card overflow-hidden">
            <div class="flex justify-between items-center px-5 py-4"><h2 class="m-0 text-lg font-bold">Recent orders</h2><a href="{{ route('admin.orders.index', ['q' => $user->username]) }}" class="text-sm">All orders</a></div>
            <div class="table-wrap"><table class="table min-w-[560px]">
                <thead><tr><th>Order</th><th>Service</th><th class="text-right">Price</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr>
                        <td class="whitespace-nowrap"><a href="{{ route('admin.orders.show', $o) }}" class="font-semibold">#{{ $o->order_no }}</a><div class="text-xs text-muted">{{ $o->created_at->format('d M H:i') }}</div></td>
                        <td>{{ $o->service_name }}<div class="text-xs text-muted font-mono">{{ $o->imei }}</div></td>
                        <td class="text-right whitespace-nowrap">{{ money($o->price) }}</td>
                        <td><x-status-badge :status="$o->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center py-6">No orders.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>

        <section class="card overflow-hidden">
            <h2 class="m-0 px-5 py-4 text-lg font-bold">Wallet history</h2>
            <div class="table-wrap"><table class="table min-w-[560px]">
                <thead><tr><th>Date</th><th>Description</th><th class="text-right">Amount</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                @forelse ($wallet as $t)
                    <tr>
                        <td class="whitespace-nowrap text-muted">{{ $t->created_at->format('d M H:i') }}</td>
                        <td>{{ $t->description }}</td>
                        <td @class(['text-right font-semibold whitespace-nowrap', 'text-ok' => $t->amount > 0, 'text-danger' => $t->amount < 0])>{{ $t->amount > 0 ? '+' : '−' }}{{ money(abs($t->amount)) }}</td>
                        <td class="text-right whitespace-nowrap">{{ money($t->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center py-6">No wallet activity.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>

        <section class="card overflow-hidden">
            <h2 class="m-0 px-5 py-4 text-lg font-bold">Top-ups</h2>
            <div class="table-wrap"><table class="table min-w-[520px]">
                <thead><tr><th>Date</th><th>Method</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td class="whitespace-nowrap"><a href="{{ route('admin.payments.show', $p) }}">{{ $p->created_at->format('d M H:i') }}</a></td>
                        <td>{{ $p->method?->name }}</td>
                        <td class="text-right">{{ money($p->amount) }}</td>
                        <td><x-status-badge :status="$p->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted text-center py-6">No top-ups.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>

    <aside class="flex-[1_1_360px] min-w-0 flex flex-col gap-5">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card p-5 flex flex-col gap-3.5">
            @csrf @method('PUT')
            <h2 class="m-0 text-lg font-bold">Account</h2>
            <div><label for="name" class="label text-xs">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" class="input h-10" required><x-field-error name="name" /></div>
            <div><label for="email" class="label text-xs">Email</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" class="input h-10" required><x-field-error name="email" /></div>
            <div><label for="phone" class="label text-xs">Phone</label><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="input h-10"></div>
            <div class="grid grid-cols-3 gap-2">
                <div><label for="user_group_id" class="label text-xs">Group</label>
                    <select id="user_group_id" name="user_group_id" class="input h-10 px-2">@foreach ($groups as $g)<option value="{{ $g->id }}" @selected($user->user_group_id === $g->id)>{{ $g->name }}</option>@endforeach</select></div>
                <div><label for="status" class="label text-xs">Status</label>
                    <select id="status" name="status" class="input h-10 px-2"><option value="active" @selected($user->status === 'active')>Active</option><option value="suspended" @selected($user->status === 'suspended')>Suspended</option></select></div>
                <div><label for="role" class="label text-xs">Role</label>
                    <select id="role" name="role" class="input h-10 px-2"><option value="user" @selected($user->role === 'user')>User</option><option value="admin" @selected($user->role === 'admin')>Admin</option></select></div>
            </div>
            <dl class="m-0 grid grid-cols-2 gap-2 text-xs text-muted">
                <div><dt>Referral code</dt><dd class="m-0 font-mono text-ink">{{ $user->referral_code }}</dd></div>
                <div><dt>Referred by</dt><dd class="m-0 text-ink">{{ $user->referrer?->username ?? '—' }} · {{ $stats['referrals'] }} invited</dd></div>
                <div><dt>Last login</dt><dd class="m-0 text-ink">{{ $user->last_login_at?->format('d M Y H:i') ?? 'never' }}</dd></div>
                <div><dt>Last IP</dt><dd class="m-0 font-mono text-ink">{{ $user->last_login_ip ?? '—' }}</dd></div>
            </dl>
            <button class="btn btn-primary">Save</button>
        </form>

        <form method="POST" action="{{ route('admin.users.wallet', $user) }}" class="card p-5 flex flex-col gap-3.5">
            @csrf
            <h2 class="m-0 text-lg font-bold">Adjust wallet</h2>
            <div class="grid grid-cols-[8rem_1fr] gap-2">
                <div><label for="direction" class="label text-xs">Action</label>
                    <select id="direction" name="direction" class="input h-10"><option value="credit">Add</option><option value="debit" @selected(old('direction') === 'debit')>Remove</option></select></div>
                <div><label for="amount" class="label text-xs">Amount (Ks)</label><input id="amount" name="amount" type="number" min="1" step="1" value="{{ old('amount') }}" class="input h-10" required><x-field-error name="amount" /></div>
            </div>
            <div><label for="reason" class="label text-xs">Reason (user sees it in Statement)</label><input id="reason" name="reason" value="{{ old('reason') }}" class="input h-10" placeholder="e.g. Bank transfer received manually" required><x-field-error name="reason" /></div>
            <button class="btn" onclick="return confirm('Change this user\'s wallet balance?')">Apply</button>
        </form>

        <section class="card p-5 flex flex-col gap-3">
            <h2 class="m-0 text-lg font-bold">Security</h2>
            <div class="text-sm">Google Authenticator: @if ($user->hasTwoFactor()) <strong class="text-ok">On</strong> @else <span class="text-muted">Off</span> @endif</div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.users.reset-2fa', $user) }}" onsubmit="return confirm('Turn off 2FA and sign out trusted devices for this user?')">@csrf<button class="btn h-9 text-xs" @disabled(! $user->hasTwoFactor() && $devices->isEmpty())>Reset 2FA &amp; devices</button></form>
                <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">@csrf<button class="btn h-9 text-xs">Email password reset link</button></form>
            </div>
            @if ($devices->isNotEmpty())
                <ul class="m-0 p-0 list-none flex flex-col gap-1.5 text-xs">
                    @foreach ($devices as $d)
                        <li class="px-3 py-2 rounded-lg bg-surface-2"><span class="font-mono">{{ $d->ip_address }}</span> · {{ \Illuminate\Support\Str::limit($d->user_agent, 40) }} · {{ $d->last_used_at?->diffForHumans() }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </aside>
</div>
@endsection
