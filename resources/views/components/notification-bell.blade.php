@auth
    @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
    <a href="{{ route('account.notifications') }}" class="btn btn-icon relative" aria-label="Notifications{{ $unread ? " ({$unread} unread)" : '' }}">
        <x-icon name="bell" />
        @if ($unread)
            <span class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-danger text-white text-[11px] font-bold flex items-center justify-center" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </a>
@endauth
