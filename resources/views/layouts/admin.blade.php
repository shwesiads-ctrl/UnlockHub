<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen">
<div class="flex flex-wrap min-h-screen">
    <aside class="flex-[1_1_240px] max-w-full bg-surface border-r border-line px-4 py-5 flex flex-col gap-1">
        <div class="flex items-center gap-2.5 px-2 pb-5">
            <span class="w-9 h-9 rounded-[9px] bg-brand text-white flex items-center justify-center"><x-icon name="lock" size="20" /></span>
            <span class="flex flex-col">
                <span class="font-display text-lg font-bold">{{ setting('site.name', config('app.name')) }}</span>
                <span class="text-[11px] font-bold text-brand-ink tracking-widest">ADMIN</span>
            </span>
        </div>
        @php
            $nav = [
                ['Dashboard', 'grid', 'admin.dashboard', null],
                ['Orders', 'list', 'admin.orders.index', null],
                ['Manual Queue', 'hand', 'admin.orders.manual', 'manual'],
                ['Payments', 'receipt', 'admin.payments.index', 'payments'],
                ['Services', 'phone', 'admin.services.index', null],
                ['Suppliers & API', 'plug', 'admin.suppliers.index', null],
                ['Users', 'users', 'admin.users.index', null],
                ['Groups & Pricing', 'sliders', 'admin.groups.index', null],
                ['Points', 'star', 'admin.points.index', null],
                ['Content', 'file', 'admin.posts.index', null],
                ['Reports', 'chart', 'admin.reports.index', null],
                ['Settings', 'sliders', 'admin.settings.index', null],
            ];
        @endphp
        <nav aria-label="Admin" class="flex flex-col gap-0.5">
            @foreach ($nav as [$label, $icon, $route, $badgeKey])
                <a href="{{ Route::has($route) ? route($route) : '#' }}" @class(['side-link', 'active' => request()->routeIs($route.'*')])>
                    <x-icon :name="$icon" />
                    <span class="flex-1">{{ $label }}</span>
                    @if ($badgeKey && ($adminBadges[$badgeKey] ?? 0) > 0)
                        <span class="min-w-[22px] h-[22px] px-1.5 rounded-full bg-danger text-white text-xs font-bold flex items-center justify-center">{{ $adminBadges[$badgeKey] }}</span>
                    @endif
                </a>
            @endforeach
            <a href="{{ route('account.dashboard') }}" class="side-link mt-3"><x-icon name="hand" /><span>My account</span></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="side-link w-full bg-transparent border-0 cursor-pointer text-left"><x-icon name="logout" /><span>Log out</span></button>
            </form>
        </nav>
    </aside>

    <div class="flex-[999_1_560px] min-w-0 flex flex-col">
        <header class="bg-surface border-b border-line px-4 sm:px-8 py-3 flex flex-wrap items-center gap-3">
            <div class="flex flex-col">
                <h1 class="m-0 text-2xl font-bold">{{ $title ?? 'Admin' }}</h1>
                @isset($subtitle) <span class="text-[13px] text-muted">{{ $subtitle }}</span> @endisset
            </div>
            <div class="ml-auto flex items-center gap-2">
                <form role="search" class="hidden md:flex items-center gap-2 h-11 px-3 rounded-[10px] border border-line bg-bg text-muted min-w-60">
                    <x-icon name="search" size="16" />
                    <label for="admin-search" class="sr-only">Search</label>
                    <input id="admin-search" name="q" type="text" placeholder="Order ID, IMEI, user…" class="flex-1 bg-transparent outline-none text-ink text-sm">
                </form>
                <x-theme-toggle />
                <span class="w-11 h-11 rounded-full bg-brand text-white font-bold flex items-center justify-center" aria-label="Admin account">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 2)) }}</span>
            </div>
        </header>

        <main class="px-4 sm:px-8 py-7 flex flex-col gap-5">
            @if (session('success')) <div class="badge-success rounded-xl px-4 py-3 text-sm font-medium">{{ session('success') }}</div> @endif
            @if (session('error')) <div class="badge-rejected rounded-xl px-4 py-3 text-sm font-medium">{{ session('error') }}</div> @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
