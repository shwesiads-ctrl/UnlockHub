@extends($staff ? 'layouts.admin' : 'layouts.account', ['title' => '#'.$ticket->id.' · '.$ticket->subject])
@section('content')
@php $prefix = $staff ? 'admin' : 'account'; @endphp
<div class="flex flex-wrap items-center gap-3"><a href="{{ route($prefix.'.support.index') }}" class="btn">← Support Desk</a><span class="badge badge-info">{{ $ticket->channel === 'chat' ? 'Live chat' : 'Support ticket' }}</span><span data-conversation-status class="badge badge-info">{{ ucfirst($ticket->status) }}</span>
@if ($staff)<form class="ml-auto flex gap-2" method="POST" action="{{ route('admin.support.status', $ticket) }}">@csrf @method('PUT')<select class="input h-11" name="status" aria-label="Conversation status">@foreach (['open' => 'Open', 'waiting' => 'Waiting for customer', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $key => $label)<option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>@endforeach</select><button class="btn">Update</button></form>@endif
</div>
<section class="card p-5 flex flex-wrap gap-6 text-sm">
    <div><span class="text-muted">Category</span><strong class="block mt-1">{{ \App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? 'General' }}</strong></div>
    <div><span class="text-muted">Priority</span><strong class="block mt-1">{{ ucfirst($ticket->priority) }}</strong></div>
    <div><span class="text-muted">Created</span><strong class="block mt-1">{{ $ticket->created_at->format('d M Y H:i') }}</strong></div>
    @if ($ticket->order)<div><span class="text-muted">Related order</span><a class="block mt-1 font-semibold" href="{{ route($prefix.'.orders.show', $ticket->order) }}">{{ $ticket->order->order_no }}</a></div>@endif
</section>
<section class="card support-conversation-card" data-support-conversation data-viewer="{{ $staff ? 'staff' : 'customer' }}" data-poll-url="{{ route($prefix.'.support.messages', $ticket) }}" data-after="{{ $messages->max('id') ?? 0 }}" data-closed="{{ in_array($ticket->status, ['closed', 'resolved']) ? '1' : '0' }}" data-poll="{{ $messages->currentPage() === 1 ? '1' : '0' }}">
    <div class="support-conversation-toolbar"><div><strong>{{ $ticket->channel === 'chat' ? 'Live chat' : 'Support conversation' }} #{{ $ticket->id }}</strong><span class="block mt-1 text-xs text-muted">Replies appear automatically · Support hours: {{ setting('site.support_hours', '9 AM – 10 PM') }}</span></div><span class="text-xs text-muted" data-chat-status role="status">{{ $messages->currentPage() === 1 ? 'Connecting…' : 'Viewing older messages. Return to latest messages to resume live updates.' }}</span></div>
    <div data-chat-messages class="support-messages" aria-live="polite" aria-relevant="additions">
    @foreach ($messages->getCollection()->reverse() as $message)
        <article class="support-message {{ ($staff ? $message->is_staff : ! $message->is_staff) ? 'support-message-outgoing' : '' }}" data-message-id="{{ $message->id }}"><strong class="text-xs">{{ $message->is_staff ? ($staff ? 'You' : 'Support team') : ($staff ? $ticket->user->name : 'You') }}</strong><p class="m-0 mt-2 whitespace-pre-wrap break-words">{{ $message->body }}</p>@if ($message->attachment_path)<a class="block mt-3 text-sm" href="{{ route($prefix.'.support.attachment', [$ticket, $message]) }}">Attachment: {{ $message->attachment_name }}</a>@endif<time class="block mt-2 text-xs text-muted">{{ $message->created_at->format('d M Y H:i') }}</time></article>
    @endforeach
    </div>
    <button type="button" data-chat-jump class="btn self-center" hidden>New messages ↓</button>
    {{ $messages->links() }}
    @if ($messages->currentPage() > 1)<a href="{{ route($prefix.'.support.show', $ticket) }}" class="btn self-start">Return to latest messages</a>@endif
    <form data-chat-reply method="POST" action="{{ route($prefix.'.support.reply', $ticket) }}" enctype="multipart/form-data" class="support-chat-composer">
        @csrf <input type="hidden" name="client_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <label for="reply" class="sr-only">Your message</label><textarea id="reply" name="message" class="input" rows="3" maxlength="5000" placeholder="Write a reply… (Enter to send, Shift+Enter for a new line)" required @disabled(in_array($ticket->status, ['closed', 'resolved']))>{{ old('message') }}</textarea><x-field-error name="message" />
        <div class="support-composer-footer"><label for="reply-file" class="btn h-9 text-xs {{ in_array($ticket->status, ['closed', 'resolved']) ? 'opacity-50 pointer-events-none' : 'cursor-pointer' }}">Attach file</label><input id="reply-file" name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="sr-only" data-chat-file @disabled(in_array($ticket->status, ['closed', 'resolved']))><span class="min-w-0 flex-1 text-xs text-muted" data-chat-file-name>Images or PDF · 5 MB max</span><p data-send-status class="m-0 text-sm text-muted" role="status"></p><button type="submit" class="btn btn-primary" @disabled(in_array($ticket->status, ['closed', 'resolved']))>Send message</button></div>
        <x-field-error name="attachment" />
        <p class="m-0 text-xs text-muted">Never send passwords, one-time codes or API keys.</p>
    </form>
</section>
@endsection
