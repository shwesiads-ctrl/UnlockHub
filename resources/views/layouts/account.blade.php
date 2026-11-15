<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen">
<div class="flex flex-wrap min-h-screen">
    <aside class="flex-[1_1_240px] max-w-full bg-surface border-r border-line px-4 py-5 flex flex-col gap-1">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 no-underline text-ink px-2 pb-5">
            <span class="w-9 h-9 rounded-[9px] bg-brand text-white flex items-center justify-center"><x-icon name="lock" size="20" /></span>
            <span class="font-display text-xl font-bold">{{ setting('site.name', config('app.name')) }}</span>
        </a>
        @php
            $nav = [
                ['Dashboard', 'grid', 'account.dashboard'],
                ['Place Order', 'phone', 'services.index'],
                ['Order History', 'list', 'account.orders.index'],
                ['Add Funds', 'wallet', 'account.funds.index'],
                ['Statement', 'receipt', 'account.statement'],
                ['Points', 'star', 'account.points'],
                ['Notifications', 'bell', 'account.notifications'],
                ['API Access', 'plug', 'account.api'],
                ['Settings & 2FA', 'sliders', 'account.security'],
            ];
        @endphp
        <nav aria-label="Account" class="flex flex-col gap-0.5">
            @foreach ($nav as [$label, $icon, $route])
                <a href="{{ Route::has($route) ? route($route) : '#' }}" @class(['side-link', 'active' => request()->routeIs($route)])>
                    <x-icon :name="$icon" /><span>{{ __($label) }}</span>
                </a>
            @endforeach
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="side-link mt-3"><x-icon name="server" /><span>{{ __('Admin panel') }}</span></a>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="side-link w-full bg-transparent border-0 cursor-pointer text-left"><x-icon name="logout" /><span>{{ __('Log out') }}</span></button>
            </form>
        </nav>
    </aside>

    <div class="flex-[999_1_560px] min-w-0 flex flex-col">
        <header class="bg-surface border-b border-line px-4 sm:px-8 py-3 flex flex-wrap items-center gap-3">
            <div class="flex flex-col">
                <span class="font-display text-[22px] font-bold">{{ __($title ?? 'Account') }}</span>
                <span class="text-[13px] text-muted">{{ __('Group') }}: {{ auth()->user()->group?->name ?? '—' }}</span>
            </div>
            <div class="ml-auto flex items-center gap-2 flex-wrap">
                <span class="btn bg-ok-soft text-ok border-transparent font-bold"><x-icon name="wallet" size="16" />{{ money(auth()->user()->balance) }}</span>
                <span class="btn bg-point-soft text-point border-transparent font-bold"><x-icon name="star" size="16" />{{ number_format(auth()->user()->points) }} pts</span>
                <x-notification-bell />
                <x-theme-toggle />
            </div>
        </header>

        <main class="px-4 sm:px-8 py-7 flex flex-col gap-5">
            @if (session('success')) <div class="badge-success rounded-xl px-4 py-3 text-sm font-medium" role="status">{{ session('success') }}</div> @endif
            @if (session('error')) <div class="badge-rejected rounded-xl px-4 py-3 text-sm font-medium" role="alert">{{ session('error') }}</div> @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
