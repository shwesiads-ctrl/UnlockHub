<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <link rel="alternate" type="application/rss+xml" title="RSS" href="{{ route('rss') }}">
</head>
<body class="min-h-screen flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:p-3">Skip to content</a>

    @if ($announcement ?? null)
        <div class="badge-{{ $announcement->style === 'danger' ? 'rejected' : ($announcement->style === 'warning' ? 'pending' : 'info') }} text-sm font-medium text-center py-2 px-4">
            {{ $announcement->message }}
            @if ($announcement->link) <a href="{{ $announcement->link }}" class="underline ml-1">More</a> @endif
        </div>
    @endif

    <header class="bg-surface border-b border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-8 py-3 flex flex-wrap items-center gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 no-underline text-ink">
                <span class="w-9 h-9 rounded-[9px] bg-brand text-white flex items-center justify-center"><x-icon name="lock" size="20" /></span>
                <span class="font-display text-xl font-bold">{{ setting('site.name', config('app.name')) }}</span>
            </a>

            <nav aria-label="Main" class="flex flex-wrap gap-1 sm:ml-3">
                <a href="{{ route('home') }}" @class(['nav-link', 'active' => request()->routeIs('home')])>Home</a>
                <a href="{{ route('services.index') }}" @class(['nav-link', 'active' => request()->routeIs('services.*')])>Services &amp; Pricing</a>
                <a href="{{ route('track') }}" @class(['nav-link', 'active' => request()->routeIs('track')])>Track Order</a>
                <a href="{{ route('kb.index') }}" @class(['nav-link', 'active' => request()->routeIs('kb.*')])>Knowledge Base</a>
                <a href="{{ route('blog.index') }}" @class(['nav-link', 'active' => request()->routeIs('blog.*')])>Blog</a>
                <a href="{{ route('contact') }}" @class(['nav-link', 'active' => request()->routeIs('contact')])>Contact</a>
            </nav>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <button type="button" class="btn text-[13px]">MM / EN</button>
                <x-theme-toggle />
                @auth
                    <a href="{{ route('account.dashboard') }}" class="btn bg-ok-soft text-ok border-transparent font-bold hover:text-ok"><x-icon name="wallet" size="16" />{{ money(auth()->user()->balance) }}</a>
                    <a href="{{ route('account.dashboard') }}" class="btn bg-point-soft text-point border-transparent font-bold hover:text-point"><x-icon name="star" size="16" />{{ number_format(auth()->user()->points) }} pts</a>
                    <a href="{{ route('account.dashboard') }}" class="w-11 h-11 rounded-full bg-brand text-white hover:text-white font-bold no-underline flex items-center justify-center" aria-label="My account">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</a>
                @else
                    <a href="{{ route('login') }}" class="btn">Login</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main" class="flex-1">
        @if (session('success'))
            <div class="max-w-7xl mx-auto px-4 sm:px-8 pt-6"><div class="badge-success rounded-xl px-4 py-3 text-sm font-medium">{{ session('success') }}</div></div>
        @endif
        @if (session('error'))
            <div class="max-w-7xl mx-auto px-4 sm:px-8 pt-6"><div class="badge-rejected rounded-xl px-4 py-3 text-sm font-medium">{{ session('error') }}</div></div>
        @endif

        @yield('content')
    </main>

    <footer class="mt-12 bg-surface border-t border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-8 py-10 flex flex-wrap gap-10">
            <div class="flex-[1_1_280px] flex flex-col gap-2.5">
                <span class="font-display text-xl font-bold">{{ setting('site.name', config('app.name')) }}</span>
                <p class="text-sm text-muted leading-relaxed m-0">Check IMEI and model carefully — wrong entries are non-refundable. Orders already In Process cannot be cancelled.</p>
            </div>
            <div class="flex-[1_1_160px] flex flex-col gap-2.5 text-sm"><span class="font-bold">Support</span><a href="{{ route('kb.index') }}">Knowledge Base</a><a href="{{ route('track') }}">Track Order</a><a href="{{ route('downloads') }}">Downloads</a><a href="{{ route('contact') }}">Contact Us</a></div>
            <div class="flex-[1_1_160px] flex flex-col gap-2.5 text-sm"><span class="font-bold">Company</span><a href="{{ route('blog.index') }}">Blog</a><a href="{{ route('testimonials') }}">Testimonials</a><a href="{{ route('rss') }}">RSS</a>@if ($terms = setting('pages.terms_slug'))<a href="{{ route('kb.show', $terms) }}">Terms &amp; Refunds</a>@endif</div>
            <div class="flex-[1_1_160px] flex flex-col gap-2.5 text-sm"><span class="font-bold">Channels</span>
                @foreach (['telegram' => 'Telegram', 'viber' => 'Viber', 'facebook' => 'Facebook', 'youtube' => 'YouTube'] as $k => $label)
                    @if ($url = setting('social.'.$k)) <a href="{{ $url }}" rel="noopener" target="_blank">{{ $label }}</a> @endif
                @endforeach
            </div>
        </div>
    </footer>
</body>
</html>
