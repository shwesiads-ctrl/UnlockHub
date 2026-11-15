@extends('layouts.account', ['title' => 'Notifications'])

@section('content')
<section class="card p-5 flex flex-wrap gap-3 items-center" data-push-panel hidden>
    <div class="flex-1 min-w-56">
        <h2 class="m-0 text-base font-bold">{{ __('Phone notifications') }}</h2>
        <p class="m-0 text-sm text-muted" data-push-status>…</p>
    </div>
    <button type="button" class="btn btn-primary" data-push-on hidden>{{ __('Turn on') }}</button>
    <button type="button" class="btn" data-push-off hidden>{{ __('Turn off') }}</button>
</section>
<section class="card p-4 flex flex-wrap gap-3 items-center" data-install-app hidden>
    <span class="flex-1 text-sm">{{ __('Install the app on this device for one-tap access.') }}</span>
    <button type="button" class="btn btn-primary" data-install-app>{{ __('Install app') }}</button>
</section>
<div class="flex flex-wrap gap-2 items-center">
    <p class="m-0 text-sm text-muted flex-1">{{ __('Order results, top-up decisions and points. Email copies can be turned off in') }} <a href="{{ route('account.security') }}">{{ __('Settings') }}</a>.</p>
    @if (auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('account.notifications.read-all') }}">@csrf<button class="btn h-9 text-[13px]">{{ __('Mark all as read') }}</button></form>
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
            @unless ($n->read_at) <span class="sr-only">{{ __('(unread)') }}</span> @endunless
        </a>
    @empty
        <p class="m-0 p-10 text-center text-muted">{{ __('No notifications yet.') }}</p>
    @endforelse
</section>

{{ $notifications->links() }}
@endsection
