@extends($staff ? 'layouts.admin' : 'layouts.account', ['title' => 'Support Desk & Live Chat'])
@section('content')
<div class="profile-support-intro card p-6"><div><span class="badge badge-info">Live support center</span><h2 class="text-2xl font-bold mt-3 mb-2">How can we help?</h2><p class="m-0 text-muted text-sm">{{ $staff ? 'Review customer tickets and reply to live conversations.' : 'Ask about orders, payments or your account. Our team replies during support hours.' }}</p></div><div class="flex flex-wrap items-center gap-3"><span class="badge badge-info">{{ setting('site.support_hours', '9 AM – 10 PM') }}</span><a class="btn" href="{{ route(($staff ? 'admin' : 'account').'.tickets.index') }}">Order support tickets →</a></div></div>
@unless ($staff)
<div class="settings-fields">
    <form method="POST" action="{{ route('account.support.store') }}" enctype="multipart/form-data" class="card p-6 flex flex-col gap-4">
        @csrf <input type="hidden" name="channel" value="ticket">
        <h2 class="m-0 text-lg font-bold">Open a support ticket</h2>
        <p class="m-0 text-sm text-muted">Choose a topic and include the details our team needs to investigate.</p>
        <div class="settings-fields">
            <div><label for="ticket-category" class="label">Category</label><select id="ticket-category" name="category" class="input" required>@foreach (\App\Models\SupportTicket::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category', 'general') === $key)>{{ $label }}</option>@endforeach</select><x-field-error name="category" /></div>
            <div><label for="ticket-priority" class="label">Priority</label><select id="ticket-priority" name="priority" class="input">@foreach (\App\Models\SupportTicket::PRIORITIES as $key => $label)<option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>@endforeach</select><x-field-error name="priority" /></div>
        </div>
        <div><label for="ticket-order" class="label">Related order number <span class="text-muted font-normal">(optional)</span></label><input id="ticket-order" name="order_no" value="{{ old('order_no') }}" class="input" placeholder="UH…"><p class="m-0 mt-1 text-xs text-muted">Only orders belonging to your account can be linked.</p><x-field-error name="order_no" /></div>
        <div><label class="label" for="support-subject">Subject</label><input id="support-subject" name="subject" value="{{ old('subject') }}" class="input" maxlength="150" required><x-field-error name="subject" /></div>
        <div><label class="label" for="support-message">Your message</label><textarea id="support-message" name="message" class="input" rows="4" maxlength="5000" placeholder="Include your order number or payment reference. Never share passwords or API keys." required>{{ old('message') }}</textarea><x-field-error name="message" /></div>
        <div><label for="ticket-file" class="label">Screenshot / document <span class="text-muted font-normal">(optional)</span></label><input id="ticket-file" type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf" class="block w-full text-sm"><p class="m-0 mt-2 text-xs text-muted">JPG, PNG, WebP or PDF up to 5 MB. Visible only to you and support staff.</p><x-field-error name="attachment" /></div>
        <button class="btn btn-primary self-start">Submit support ticket</button>
    </form>
    @if ($activeChat)
        @php $lastChatMessage = $activeChat->messages->first(); @endphp
        <section class="card live-chat-resume">
            <div class="live-chat-heading">
                <span class="live-chat-symbol"><x-icon name="chat" size="22" /></span>
                <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="m-0 text-lg font-bold">Live chat</h2><span class="badge {{ $activeChat->status === 'waiting' ? 'badge-pending' : 'badge-success' }}">{{ $activeChat->status === 'waiting' ? 'Reply needed' : 'In progress' }}</span></div>
                    <p class="m-0 mt-1 text-sm text-muted">Conversation #{{ $activeChat->id }} · Updated {{ $activeChat->updated_at->diffForHumans() }}</p></div>
            </div>
            @if ($lastChatMessage)
                <div class="live-chat-last-message"><span class="text-xs font-semibold text-muted">{{ $lastChatMessage->is_staff ? 'Support team replied' : 'Your last message' }}</span><p class="m-0 mt-1 text-sm line-clamp-2 whitespace-pre-wrap break-words">{{ $lastChatMessage->body }}</p></div>
            @endif
            <p class="m-0 text-sm text-muted">Messages refresh automatically while the conversation is open.</p>
            <a href="{{ route('account.support.show', $activeChat) }}" class="btn btn-primary self-start">Continue conversation →</a>
        </section>
    @else
        <form method="POST" action="{{ route('account.support.store') }}" enctype="multipart/form-data" class="card live-chat-start">
            @csrf <input type="hidden" name="channel" value="chat"><input type="hidden" name="subject" value="Live chat">
            <div class="live-chat-heading">
                <span class="live-chat-symbol"><x-icon name="chat" size="22" /></span>
                <div><h2 class="m-0 text-lg font-bold">Start a live chat</h2><p class="m-0 mt-1 text-sm text-muted">Support hours: {{ setting('site.support_hours', '9 AM – 10 PM') }}</p></div>
            </div>
            @if ($latestChat)
                <a class="text-xs self-start" href="{{ route('account.support.show', $latestChat) }}">View your previous conversation #{{ $latestChat->id }} →</a>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label" for="chat-category">Topic</label><select id="chat-category" name="category" class="input h-11">@foreach (\App\Models\SupportTicket::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category', 'general') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="label" for="chat-order">Related order <span class="text-muted font-normal">(optional)</span></label><input id="chat-order" name="order_no" value="{{ old('order_no') }}" class="input h-11" placeholder="UH…"><x-field-error name="order_no" /></div>
            </div>
            <div><label class="label" for="chat-message">Your message</label><textarea id="chat-message" name="message" class="input" rows="3" maxlength="5000" placeholder="Tell us what happened. Never include passwords or API keys." required>{{ old('message') }}</textarea><x-field-error name="message" /></div>
            <div><label for="chat-file" class="label">Attach a screenshot or document <span class="text-muted font-normal">(optional, up to 5 MB)</span></label><input id="chat-file" name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"><p class="m-0 mt-1 text-xs text-muted">JPG, PNG, WebP or PDF. Only you and support staff can see it.</p><x-field-error name="attachment" /></div>
            <button class="btn btn-primary self-start">Start chat →</button>
            <p class="m-0 text-xs text-muted">If support is away, your message will stay here and you can return later.</p>
        </form>
    @endif
</div>
@endunless
<form method="GET" class="card p-4 flex flex-wrap items-end gap-3">
    <div><label class="label text-xs" for="filter-status">Status</label><select id="filter-status" name="status" class="input h-10"><option value="">All statuses</option>@foreach (['open' => 'Open', 'waiting' => 'Waiting for customer', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div><label class="label text-xs" for="filter-category">Category</label><select id="filter-category" name="category" class="input h-10"><option value="">All categories</option>@foreach (\App\Models\SupportTicket::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>@endforeach</select></div><button class="btn">Filter conversations</button>
</form>
<section class="card table-wrap"><table class="table"><thead><tr><th>Conversation</th>@if ($staff)<th>Customer</th>@endif<th>Category</th><th>Priority</th><th>Channel</th><th>Status</th><th>Updated</th></tr></thead><tbody>
@forelse ($tickets as $ticket)
<tr><td><a href="{{ route(($staff ? 'admin' : 'account').'.support.show', $ticket) }}" class="font-semibold">#{{ $ticket->id }} · {{ $ticket->subject }}</a></td>@if ($staff)<td>{{ $ticket->user->username }}</td>@endif<td>{{ \App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? 'General' }}</td><td><span class="badge {{ $ticket->priority === 'urgent' ? 'badge-rejected' : 'badge-info' }}">{{ ucfirst($ticket->priority) }}</span></td><td>{{ $ticket->channel === 'chat' ? 'Live chat' : 'Ticket' }}</td><td><span class="badge {{ $ticket->status === 'open' ? 'badge-info' : 'badge-success' }}">{{ $ticket->status === 'waiting' ? 'Waiting for customer' : ucfirst($ticket->status) }}</span></td><td class="text-muted whitespace-nowrap">{{ $ticket->updated_at->diffForHumans() }}</td></tr>
@empty <tr><td colspan="{{ $staff ? 7 : 6 }}" class="text-center text-muted py-8">No conversations yet.</td></tr> @endforelse
</tbody></table></section>
{{ $tickets->links() }}
@endsection
