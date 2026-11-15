<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen">
<div class="flex flex-wrap lg:flex-nowrap min-h-screen">
    <div class="nav-backdrop" aria-hidden="true"></div>
    <aside id="side-nav" class="side-nav flex-[1_1_240px] lg:flex-[0_0_260px] max-w-full bg-surface border-r border-line px-4 py-5 flex flex-col gap-1">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 no-underline text-ink px-2 pb-5">
            <x-site-logo />
        </a>
        @php
            $nav = [
                ['Dashboard', 'grid', 'account.dashboard'],
                ['My Profile', 'user', 'account.profile'],
                ['Place Order', 'phone', 'services.index'],
                ['Favourites', 'heart', 'account.favorites'],
                ['Order History', 'list', 'account.orders.index'],
                ['Add Funds', 'wallet', 'account.funds.index'],
                ['Statement', 'receipt', 'account.statement'],
                ['Support', 'chat', 'account.tickets.index'],
                ['Points', 'star', 'account.points'],
                ['Notifications', 'bell', 'account.notifications'],
                ['API Access', 'plug', 'account.api'],
                ['Earnings', 'chart', 'account.earnings'],
                ['Settings & 2FA', 'sliders', 'account.security'],
                ['Live Chat', 'users', 'account.support.index'],
            ];
        @endphp
        <nav aria-label="{{ __('Account') }}" class="flex flex-col gap-0.5">
            @foreach ($nav as [$label, $icon, $route])
                @continue($route === 'account.points' && ! \App\Services\PointService::systemOn())
                @continue($route === 'account.earnings' && ! (\App\Services\AffiliateService::enabled() || auth()->user()->isReseller()))
                @php $isActive = request()->routeIs($route) || ($route === 'account.tickets.index' && request()->routeIs('account.tickets.*')); @endphp
                <a href="{{ Route::has($route) ? route($route) : '#' }}" @class(['side-link', 'active' => $isActive]) @if ($isActive) aria-current="page" @endif>
                    <x-icon :name="$icon" /><span>{{ __($label) }}</span>
                </a>
            @endforeach
            @if (auth()->user()->isReseller())
                <a href="{{ route('reseller.dashboard') }}" @class(['side-link mt-3', 'active' => request()->routeIs('reseller.*')])><x-icon name="users" /><span>{{ __('Reseller panel') }}</span></a>
            @elseif (! auth()->user()->parent_id && \App\Services\ResellerService::enabled())
                <a href="{{ route('account.reseller.apply') }}" @class(['side-link mt-3', 'active' => request()->routeIs('account.reseller.*')])><x-icon name="users" /><span>{{ __('Become a reseller') }}</span></a>
            @endif
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
        <header class="bg-surface border-b border-line px-4 sm:px-8 py-3 flex flex-wrap items-center gap-3 lg:sticky lg:top-0 z-30">
            <button type="button" class="btn btn-icon lg:hidden" data-nav-toggle aria-controls="side-nav" aria-label="{{ __('Menu') }}"><x-icon name="menu" /></button>
            <div class="flex flex-col">
                <span class="font-display text-[22px] font-bold">{{ __($title ?? 'Account') }}</span>
                <x-user-badges :user="auth()->user()" size="sm" />
            </div>
            <div class="ml-auto flex items-center gap-2 flex-wrap">
                <span class="btn bg-ok-soft text-ok border-transparent font-bold"><x-icon name="wallet" size="16" />{{ money(auth()->user()->balance) }}</span>
                @if (\App\Services\PointService::systemOn())
                    <a href="{{ route('account.points') }}" class="btn hidden sm:inline-flex bg-point-soft text-point border-transparent font-bold hover:text-point"><x-icon name="star" size="16" />{{ number_format(auth()->user()->points) }} {{ __('pts') }}</a>
                @endif
                <x-notification-bell />
                <x-locale-switcher />
                <x-theme-toggle />
                <a href="{{ route('account.profile') }}" aria-label="{{ __('My profile') }}" class="no-underline"><x-avatar :user="auth()->user()" :size="44" /></a>
            </div>
        </header>

        <main class="px-4 sm:px-8 py-7 pb-28 lg:pb-7 flex flex-col gap-5">
            @if (session('success')) <div class="badge-success rounded-xl px-4 py-3 text-sm font-medium" role="status">{{ __(session('success')) }}</div> @endif
            @if (session('error')) <div class="badge-rejected rounded-xl px-4 py-3 text-sm font-medium" role="alert">{{ __(session('error')) }}</div> @endif
            @if (session('info')) <div class="badge-info rounded-xl px-4 py-3 text-sm font-medium" role="status">{{ session('info') }}</div> @endif
            @yield('content')
        </main>
    </div>
</div>
<nav class="bottom-bar lg:hidden fixed bottom-0 inset-x-0 z-30 bg-surface border-t border-line grid grid-cols-5" aria-label="{{ __('Quick menu') }}">
    @foreach ([['Home', 'grid', 'account.dashboard'], ['New order', 'phone', 'services.index'], ['My orders', 'list', 'account.orders.index'], ['Funds', 'wallet', 'account.funds.index'], ['Support', 'chat', 'account.tickets.index']] as [$l, $i, $r])
        <a href="{{ route($r) }}" @class(['active' => request()->routeIs($r) || ($r === 'account.orders.index' && request()->routeIs('account.orders.*')) || ($r === 'account.tickets.index' && request()->routeIs('account.tickets.*'))])><x-icon :name="$i" size="20" />{{ __($l) }}</a>
    @endforeach
</nav>
</body>
</html>
