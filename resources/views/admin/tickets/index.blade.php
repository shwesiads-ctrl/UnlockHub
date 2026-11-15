@extends('layouts.admin', ['title' => 'Tickets', 'subtitle' => 'Customer support'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    @foreach (['active' => 'Active', 'needs_reply' => 'Needs reply ('.$counts['needs_reply'].')'] + \App\Models\Ticket::STATUSES as $k => $l)
        <a href="{{ route('admin.tickets.index', array_merge(request()->except('page'), ['status' => $k])) }}" @class(['btn h-9 text-[13px]', 'btn-primary' => $f['status'] === $k])>{{ $l }}</a>
    @endforeach
    @can('tickets.assign') <a href="{{ route('admin.canned.index') }}" class="btn h-9 text-[13px] ml-auto">Saved replies</a> @endcan
</div>

<form method="GET" class="card p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="status" value="{{ $f['status'] }}">
    <div class="flex-1 min-w-52"><label for="q" class="label text-xs">Search</label><input id="q" name="q" value="{{ $f['q'] ?? '' }}" class="input h-10" placeholder="Ticket no., subject, username, order no., IMEI"></div>
    <div><label for="priority" class="label text-xs">Priority</label>
        <select id="priority" name="priority" class="input h-10 w-auto"><option value="">Any</option>@foreach (\App\Models\Ticket::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected(($f['priority'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div><label for="category" class="label text-xs">Topic</label>
        <select id="category" name="category" class="input h-10 w-auto"><option value="">Any</option>@foreach (\App\Models\Ticket::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(($f['category'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div><label for="assigned" class="label text-xs">Assigned</label>
        <select id="assigned" name="assigned" class="input h-10 w-auto"><option value="">Anyone</option><option value="me" @selected(($f['assigned'] ?? '') === 'me')>Me ({{ $counts['mine'] }})</option><option value="none" @selected(($f['assigned'] ?? '') === 'none')>Unassigned</option></select></div>
    <button class="btn h-10">Filter</button>
</form>

<section class="card table-wrap">
    <table class="table min-w-[900px]">
        <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Last reply</th></tr></thead>
        <tbody>
        @forelse ($tickets as $t)
            <tr @class(['font-semibold' => $t->awaiting_staff && ! in_array($t->status, ['resolved', 'closed'])])>
                <td class="font-mono whitespace-nowrap">
                    @if ($t->awaiting_staff && ! in_array($t->status, ['resolved', 'closed'])) <span class="inline-block w-2 h-2 rounded-full bg-danger mr-1" title="Waiting for staff"></span> @endif
                    <a href="{{ route('admin.tickets.show', $t) }}">#{{ $t->ticket_no }}</a>
                </td>
                <td><a href="{{ route('admin.tickets.show', $t) }}" class="text-ink">{{ \Illuminate\Support\Str::limit($t->subject, 60) }}</a>
                    <div class="text-xs text-muted font-normal">{{ \App\Models\Ticket::CATEGORIES[$t->category] }}@if ($t->order) · #{{ $t->order->order_no }}@endif</div></td>
                <td class="font-normal">{{ $t->user?->username }}</td>
                <td><span @class(['badge', 'badge-rejected' => $t->priority === 'urgent', 'badge-pending' => $t->priority === 'high', 'badge-info' => $t->priority === 'normal', 'bg-surface-2 text-muted' => $t->priority === 'low'])>{{ \App\Models\Ticket::PRIORITIES[$t->priority] }}</span></td>
                <td><span class="badge {{ $t->badgeClass() }}">{{ $t->statusLabel() }}</span></td>
                <td class="font-normal text-muted">{{ $t->assignee?->username ?? '—' }}</td>
                <td class="font-normal text-muted whitespace-nowrap">{{ $t->last_reply_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-10">No tickets.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
{{ $tickets->links() }}
@endsection
