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
<section class="card p-5 flex flex-col gap-4" data-support-conversation data-poll-url="{{ route($prefix.'.support.messages', $ticket) }}" data-after="{{ $messages->max('id') ?? 0 }}" data-closed="{{ in_array($ticket->status, ['closed', 'resolved']) ? '1' : '0' }}" data-poll="{{ $messages->currentPage() === 1 ? '1' : '0' }}">
    <p class="m-0 text-xs text-muted" data-chat-status role="status">Messages refresh every 5 seconds. Support hours: {{ setting('site.support_hours', '9 AM – 10 PM') }}.</p>
    <div data-chat-messages class="support-messages" aria-live="polite" aria-relevant="additions">
    @foreach ($messages->getCollection()->reverse() as $message)
        <article class="support-message {{ $message->is_staff ? 'support-staff' : '' }}" data-message-id="{{ $message->id }}"><strong class="text-xs">{{ $message->is_staff ? 'Support team' : 'Customer' }}</strong><p class="m-0 mt-2 whitespace-pre-wrap break-words">{{ $message->body }}</p>@if ($message->attachment_path)<a class="block mt-3 text-sm" href="{{ route($prefix.'.support.attachment', [$ticket, $message]) }}">Download: {{ $message->attachment_name }}</a>@endif<time class="block mt-2 text-xs text-muted">{{ $message->created_at->format('d M Y H:i') }}</time></article>
    @endforeach
    </div>
    <button type="button" data-chat-jump class="btn self-center" hidden>New messages ↓</button>
    {{ $messages->links() }}
    @if ($messages->currentPage() > 1)<a href="{{ route($prefix.'.support.show', $ticket) }}" class="btn self-start">Return to latest messages</a>@endif
    <form data-chat-reply method="POST" action="{{ route($prefix.'.support.reply', $ticket) }}" enctype="multipart/form-data" class="flex flex-col gap-3 border-t border-line pt-4">
        @csrf <input type="hidden" name="client_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <label for="reply" class="label">Reply</label><textarea id="reply" name="message" class="input" rows="3" maxlength="5000" required @disabled(in_array($ticket->status, ['closed', 'resolved']))>{{ old('message') }}</textarea><x-field-error name="message" />
        <label class="text-sm" for="reply-file">Attach image or PDF (up to 5 MB)</label><input id="reply-file" name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" @disabled(in_array($ticket->status, ['closed', 'resolved']))><x-field-error name="attachment" />
        <p data-send-status class="m-0 text-sm text-muted" role="status"></p>
        <button type="submit" class="btn btn-primary self-end" @disabled(in_array($ticket->status, ['closed', 'resolved']))>Send message</button>
    </form>
</section>
@endsection
