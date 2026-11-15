@extends('layouts.admin', ['title' => 'Ticket #'.$ticket->ticket_no, 'subtitle' => $ticket->subject])

@section('content')
<div class="flex flex-wrap gap-5 items-start">
    <section class="flex-[999_1_560px] min-w-0 flex flex-col gap-4">
        @include('tickets._thread', ['messages' => $ticket->messages, 'staffView' => true, 'fileRoute' => 'admin.tickets.attachment'])

        @can('tickets.reply')
        <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="card p-5 flex flex-col gap-3" data-submit-once data-ticket-reply>
            @csrf
            <div class="flex flex-wrap gap-2 items-end">
                <label for="message" class="label m-0 flex-1">Reply</label>
                @if ($canned->isNotEmpty())
                    <label for="canned" class="sr-only">Saved reply</label>
                    <select id="canned" class="input h-9 w-auto text-sm" data-canned>
                        <option value="">Insert saved reply…</option>
                        @foreach ($canned as $c) <option value="{{ $c->body }}">{{ $c->title }}</option> @endforeach
                    </select>
                @endif
            </div>
            <textarea id="message" name="message" rows="6" maxlength="5000" class="input" required>{{ old('message') }}</textarea>
            <x-field-error name="message" />
            <input name="files[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt" class="input py-2" aria-label="Attach files">
            <div class="flex flex-wrap gap-3 items-center">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="internal" value="1" class="w-5 h-5 accent-[var(--uh-brand)]">Internal note (customer can't see)</label>
                <label for="reply_status" class="text-sm text-muted ml-auto">Then set status</label>
                <select id="reply_status" name="status" class="input h-10 w-auto text-sm">
                    <option value="">Auto (Waiting for Customer)</option>
                    @foreach (\App\Models\Ticket::STATUSES as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                </select>
                <button class="btn btn-primary">Send</button>
            </div>
            <p class="m-0 text-xs text-muted">Placeholders: <code>{name}</code> <code>{ticket}</code> <code>{order}</code></p>
        </form>
        @endcan
    </section>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-4">
        <div class="card p-5 flex flex-col gap-3 text-sm">
            <div class="flex justify-between"><span class="text-muted">Status</span><span class="badge {{ $ticket->badgeClass() }}">{{ $ticket->statusLabel() }}</span></div>
            <div class="flex justify-between"><span class="text-muted">Topic</span><span>{{ \App\Models\Ticket::CATEGORIES[$ticket->category] }}</span></div>
            <div class="flex justify-between"><span class="text-muted">Opened</span><span>{{ $ticket->created_at->format('d M Y H:i') }}</span></div>
            @can('tickets.reply')
            <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}" class="grid grid-cols-2 gap-2 pt-2 border-t border-line">
                @csrf @method('PUT')
                <div><label for="st" class="label text-xs">Status</label><select id="st" name="status" class="input h-10 text-sm">@foreach (\App\Models\Ticket::STATUSES as $k => $l)<option value="{{ $k }}" @selected($ticket->status === $k)>{{ $l }}</option>@endforeach</select></div>
                <div><label for="pr" class="label text-xs">Priority</label><select id="pr" name="priority" class="input h-10 text-sm">@foreach (\App\Models\Ticket::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected($ticket->priority === $k)>{{ $l }}</option>@endforeach</select></div>
                <button class="btn col-span-2 h-9 text-sm">Save</button>
            </form>
            @endcan
            @can('tickets.assign')
            <form method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" class="flex gap-2 items-end">
                @csrf @method('PUT')
                <div class="flex-1"><label for="as" class="label text-xs">Assigned to</label>
                    <select id="as" name="assigned_to" class="input h-10 text-sm"><option value="">Nobody</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($ticket->assigned_to === $s->id)>{{ $s->name }} ({{ \App\Support\Permissions::ROLES[$s->staffRole()] ?? '' }})</option>@endforeach</select></div>
                <button class="btn h-10 text-sm">Assign</button>
            </form>
            @else
                <div class="flex justify-between"><span class="text-muted">Assigned</span><span>{{ $ticket->assignee?->username ?? '—' }}</span></div>
            @endcan
        </div>

        <div class="card p-5 flex flex-col gap-2 text-sm">
            <span class="font-bold">Customer</span>
            <span>{{ $ticket->user->name }} · <span class="text-muted">{{ $ticket->user->username }}</span></span>
            @can('users.view') <a href="{{ route('admin.users.show', $ticket->user) }}" class="text-[13px]">Open customer</a> @endcan
            @if ($ticket->order)
                <div class="mt-2 p-3 rounded-lg bg-surface-2">
                    <div class="font-semibold">Order #{{ $ticket->order->order_no }}</div>
                    <div class="text-xs text-muted">{{ $ticket->order->service_name }} · {{ $ticket->order->imei }}</div>
                    <div class="mt-1"><x-status-badge :status="$ticket->order->status" /></div>
                    @can('orders.view') <a href="{{ route('admin.orders.show', $ticket->order) }}" class="text-[13px]">Open order</a> @endcan
                </div>
            @endif
        </div>

        @if ($recentOrders->isNotEmpty())
        <div class="card p-5 flex flex-col gap-2 text-[13px]">
            <span class="font-bold text-sm">Recent orders</span>
            @foreach ($recentOrders as $o)
                <div class="flex justify-between gap-2"><span class="font-mono">#{{ $o->order_no }}</span><span class="text-muted truncate">{{ \Illuminate\Support\Str::limit($o->service_name, 24) }}</span><x-status-badge :status="$o->status" /></div>
            @endforeach
        </div>
        @endif

        @if ($otherTickets->isNotEmpty())
        <div class="card p-5 flex flex-col gap-2 text-[13px]">
            <span class="font-bold text-sm">Other tickets</span>
            @foreach ($otherTickets as $o)
                <a href="{{ route('admin.tickets.show', $o) }}" class="flex justify-between gap-2"><span>#{{ $o->ticket_no }} {{ \Illuminate\Support\Str::limit($o->subject, 26) }}</span><span class="badge {{ $o->badgeClass() }}">{{ $o->statusLabel() }}</span></a>
            @endforeach
        </div>
        @endif
    </aside>
</div>
@endsection
