<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen">
<div class="flex flex-wrap lg:flex-nowrap min-h-screen">
    <div class="nav-backdrop" aria-hidden="true"></div>
    <aside id="side-nav" class="side-nav flex-[1_1_240px] lg:flex-[0_0_260px] max-w-full bg-surface border-r border-line px-4 py-5 flex flex-col gap-1">
        <div class="flex items-center gap-2.5 px-2 pb-5">
            <span class="flex flex-col gap-0.5">
                <x-site-logo />
                <span class="text-[11px] font-bold text-brand-ink tracking-widest">ADMIN</span>
            </span>
        </div>
        @php
            // [label, icon, route, badge key, active route patterns]
            $nav = [
                ['Dashboard', 'grid', 'admin.dashboard', null, ['admin.dashboard']],
                ['Orders', 'list', 'admin.orders.index', null, ['admin.orders.index', 'admin.orders.show']],
                ['Manual Queue', 'hand', 'admin.orders.manual', 'manual', ['admin.orders.manual']],
                ['Tickets', 'bell', 'admin.tickets.index', 'tickets', ['admin.tickets.*', 'admin.canned.*']],
                ['Payments', 'receipt', 'admin.payments.index', 'payments', ['admin.payments.*', 'admin.payment-methods.*']],
                ['Wallet approvals', 'wallet', 'admin.wallet-approvals.index', 'approvals', ['admin.wallet-approvals.*']],
                ['Reconciliation', 'chart', 'admin.reconcile.index', 'reconcile', ['admin.reconcile.*']],
                ['Services', 'phone', 'admin.services.index', null, ['admin.services.*', 'admin.categories.*']],
                ['Suppliers & API', 'plug', 'admin.suppliers.index', null, ['admin.suppliers.*']],
                ['Users', 'users', 'admin.users.index', null, ['admin.users.*']],
                ['Resellers', 'users', 'admin.resellers.index', 'resellers', ['admin.resellers.*']],
                ['Withdrawals', 'wallet', 'admin.withdrawals.index', 'withdrawals', ['admin.withdrawals.*']],
                ['Groups & Pricing', 'sliders', 'admin.groups.index', null, ['admin.groups.*']],
                ['Profit guard', 'chart', 'admin.pricing.index', 'pricing', ['admin.pricing.*']],
                ['Points', 'star', 'admin.points.index', null, ['admin.points.*']],
                ['Popups', 'bell', 'admin.popups.edit', null, ['admin.popups.*']],
                ['Content', 'file', 'admin.posts.index', null, ['admin.posts.*', 'admin.testimonials.*', 'admin.downloads.*', 'admin.announcements.*']],
                ['Reports', 'chart', 'admin.reports.index', null, ['admin.reports.*']],
                ['Activity Log', 'clock', 'admin.activity.index', null, ['admin.activity.*']],
                ['Staff', 'lock', 'admin.staff.index', null, ['admin.staff.*']],
                ['Settings', 'sliders', 'admin.settings.index', null, ['admin.settings.*']],
            ];
            // Only show what this staff member may open
            $nav = array_values(array_filter($nav, fn ($item) => \App\Support\Permissions::allowsRoute(auth()->user(), $item[2])));
        @endphp
        <nav aria-label="{{ __('Admin') }}" class="flex flex-col gap-0.5">
            @foreach ($nav as [$label, $icon, $route, $badgeKey, $patterns])
                <a href="{{ Route::has($route) ? route($route) : '#' }}" @class(['side-link', 'active' => request()->routeIs(...$patterns)])>
                    <x-icon :name="$icon" />
                    <span class="flex-1">{{ $label }}</span>
                    @if ($badgeKey && ($adminBadges[$badgeKey] ?? 0) > 0)
                        <span class="min-w-[22px] h-[22px] px-1.5 rounded-full bg-danger text-white text-xs font-bold flex items-center justify-center">{{ $adminBadges[$badgeKey] }}</span>
                    @endif
                </a>
            @endforeach
            <a href="{{ route('account.dashboard') }}" class="side-link mt-3"><x-icon name="hand" /><span>{{ __('My account') }}</span></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="side-link w-full bg-transparent border-0 cursor-pointer text-left"><x-icon name="logout" /><span>{{ __('Log out') }}</span></button>
            </form>
        </nav>
    </aside>

    <div class="flex-[999_1_560px] min-w-0 flex flex-col">
        <header class="bg-surface border-b border-line px-4 sm:px-8 py-3 flex flex-wrap items-center gap-3 sticky top-0 z-30">
            <button type="button" class="btn btn-icon lg:hidden" data-nav-toggle aria-controls="side-nav" aria-label="{{ __('Menu') }}"><x-icon name="menu" /></button>
            <div class="flex flex-col">
                <h1 class="m-0 text-2xl font-bold">{{ $title ?? 'Admin' }}</h1>
                @isset($subtitle) <span class="text-[13px] text-muted">{{ $subtitle }}</span> @endisset
            </div>
            <div class="ml-auto flex items-center gap-2">
                <form role="search" action="{{ route('admin.orders.index') }}" class="search-box hidden md:flex items-center gap-2 h-11 px-3 rounded-[10px] border border-line bg-bg text-muted min-w-60">
                    <x-icon name="search" size="16" />
                    <label for="admin-search" class="sr-only">{{ __('Search') }}</label>
                    <input id="admin-search" name="q" type="text" placeholder="{{ __('Order ID, IMEI, user…') }}" class="flex-1 bg-transparent outline-none text-ink text-sm">
                </form>
                <x-locale-switcher />
                <x-theme-toggle />
                <a href="{{ route('account.profile') }}" aria-label="{{ __('My profile') }}" class="no-underline"><x-avatar :user="auth()->user()" :size="44" /></a>
            </div>
        </header>

        <main class="px-4 sm:px-8 py-7 flex flex-col gap-5">
            @if (session('success')) <div class="badge-success rounded-xl px-4 py-3 text-sm font-medium">{{ session('success') }}</div> @endif
            @if (session('info')) <div class="badge-info rounded-xl px-4 py-3 text-sm font-medium">{{ session('info') }}</div> @endif
            @if (session('error')) <div class="badge-rejected rounded-xl px-4 py-3 text-sm font-medium">{{ session('error') }}</div> @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
