@php $left = (int) session('login_wait_until', 0) - now()->getTimestamp(); @endphp
@if ($left > 0)
    <div class="rounded-xl px-4 py-3 bg-warn-soft text-warn text-sm flex items-center gap-3" role="alert" data-login-wait="{{ $left }}" data-ready-text="{{ __('You can try again now.') }}">
        <x-icon name="clock" size="20" />
        <span class="flex-1">{{ __('Too many wrong tries.') }} {{ __('You can try again in') }}
            <strong class="font-display text-base tabular-nums" data-login-wait-clock>{{ sprintf('%d:%02d', intdiv($left, 60), $left % 60) }}</strong></span>
    </div>
@endif
