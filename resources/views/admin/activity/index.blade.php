@extends('layouts.admin', ['title' => 'Activity Log', 'subtitle' => 'Who changed what: approvals, order results, balances, settings'])

@section('content')
<form method="GET" class="flex flex-wrap gap-2 items-center">
    <label for="action" class="sr-only">Area</label>
    <select id="action" name="action" class="input h-10 w-auto">
        <option value="">All areas</option>
        @foreach ($actions as $a) <option value="{{ $a }}" @selected(request('action') === $a)>{{ ucfirst(str_replace('_', ' ', $a)) }}</option> @endforeach
    </select>
    <label for="user" class="sr-only">By user</label>
    <input id="user" name="user" value="{{ request('user') }}" placeholder="By username…" class="input h-10 w-48">
    <button class="btn h-10">Filter</button>
</form>

<section class="card table-wrap">
    <table class="table min-w-[820px]">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Item</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="whitespace-nowrap text-muted">{{ $log->created_at->format('d M H:i') }}</td>
                <td>{{ $log->user?->username ?? 'system' }}</td>
                <td><span class="font-mono text-[13px]">{{ $log->action }}</span></td>
                <td class="text-[13px]">
                    @if ($log->subject_type && $log->subject_id)
                        @php
                            $link = match ($log->subject_type) {
                                'order' => isset($orderNos[$log->subject_id]) ? route('admin.orders.show', $orderNos[$log->subject_id]) : null,
                                'payment_request' => route('admin.payments.show', $log->subject_id),
                                'user' => route('admin.users.show', $log->subject_id),
                                default => null,
                            };
                        @endphp
                        @if ($link) <a href="{{ $link }}">{{ $log->subject_type }} #{{ $log->subject_id }}</a> @else {{ $log->subject_type }} #{{ $log->subject_id }} @endif
                    @else
                        —
                    @endif
                </td>
                <td class="text-xs text-muted max-w-sm break-words font-mono">{{ $log->properties ? \Illuminate\Support\Str::limit(json_encode($log->properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 160) : '' }}</td>
                <td class="text-xs text-muted font-mono">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-10">Nothing logged yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $logs->links() }}
@endsection
