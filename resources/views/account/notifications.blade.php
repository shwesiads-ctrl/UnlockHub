@extends('layouts.account', ['title' => 'Notifications'])

@section('content')
<div class="flex flex-wrap gap-2 items-center">
    <p class="m-0 text-sm text-muted flex-1">Order results, top-up decisions and points. Email copies can be turned off in <a href="{{ route('account.security') }}">Settings</a>.</p>
    @if (auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('account.notifications.read-all') }}">@csrf<button class="btn h-9 text-[13px]">Mark all as read</button></form>
    @endif
</div>

<section class="card overflow-hidden">
    @forelse ($notifications as $n)
        @php $tone = $n->data['tone'] ?? 'info'; @endphp
        <a href="{{ route('account.notifications.open', $n->id) }}" @class([
            'flex gap-4 items-start px-5 py-4 no-underline text-ink hover:text-ink hover:bg-surface-2 border-t border-line first:border-t-0',
            'bg-brand-soft/40' => ! $n->read_at,
        ])>
            <span @class([
                'mt-1.5 w-2.5 h-2.5 rounded-full shrink-0',
                'bg-ok' => $tone === 'success',
                'bg-danger' => $tone === 'rejected',
                'bg-warn' => $tone === 'pending',
                'bg-brand' => ! in_array($tone, ['success', 'rejected', 'pending']),
            ]) aria-hidden="true"></span>
            <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                <span @class(['text-[15px]', 'font-bold' => ! $n->read_at, 'font-medium' => $n->read_at])>{{ $n->data['title'] ?? 'Notification' }}</span>
                <span class="text-sm text-muted">{{ $n->data['body'] ?? '' }}</span>
            </span>
            <span class="text-xs text-muted whitespace-nowrap">{{ $n->created_at->diffForHumans() }}</span>
            @unless ($n->read_at) <span class="sr-only">(unread)</span> @endunless
        </a>
    @empty
        <p class="m-0 p-10 text-center text-muted">No notifications yet.</p>
    @endforelse
</section>

{{ $notifications->links() }}
@endsection
