<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <link rel="alternate" type="application/rss+xml" title="RSS" href="{{ route('rss') }}">
</head>
<body class="min-h-screen flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:p-3">{{ __('Skip to content') }}</a>

    @if ($announcement ?? null)
        <div class="badge-{{ $announcement->style === 'danger' ? 'rejected' : ($announcement->style === 'warning' ? 'pending' : 'info') }} text-sm font-medium text-center py-2 px-4">
            {{ $announcement->message }}
            @if ($announcement->link) <a href="{{ $announcement->link }}" class="underline ml-1">{{ __('More') }}</a> @endif
        </div>
    @endif

    <header class="bg-surface border-b border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-8 py-3 flex flex-wrap items-center gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 no-underline text-ink">
                <x-site-logo />
            </a>

            <button type="button" class="btn btn-icon md:hidden order-2" data-toggle-target="#main-nav" aria-controls="main-nav" aria-expanded="false" aria-label="{{ __('Menu') }}"><x-icon name="menu" /></button>
            <nav id="main-nav" aria-label="{{ __('Main') }}" class="hidden md:flex order-3 md:order-none basis-full md:basis-auto flex-col md:flex-row md:flex-wrap gap-1 md:ml-3">
                <a href="{{ route('home') }}" @class(['nav-link', 'active' => request()->routeIs('home')]) @if (request()->routeIs('home')) aria-current=\"page\" @endif>{{ __('Home') }}</a>
                <a href="{{ route('services.index') }}" @class(['nav-link', 'active' => request()->routeIs('services.*')]) @if (request()->routeIs('services.*')) aria-current=\"page\" @endif>{{ __('Services & Pricing') }}</a>
                <a href="{{ route('track') }}" @class(['nav-link', 'active' => request()->routeIs('track')]) @if (request()->routeIs('track')) aria-current=\"page\" @endif>{{ __('Track Order') }}</a>
                <a href="{{ route('kb.index') }}" @class(['nav-link', 'active' => request()->routeIs('kb.*')]) @if (request()->routeIs('kb.*')) aria-current=\"page\" @endif>{{ __('Knowledge Base') }}</a>
                <a href="{{ route('blog.index') }}" @class(['nav-link', 'active' => request()->routeIs('blog.*')]) @if (request()->routeIs('blog.*')) aria-current=\"page\" @endif>{{ __('Blog') }}</a>
                <a href="{{ route('contact') }}" @class(['nav-link', 'active' => request()->routeIs('contact')]) @if (request()->routeIs('contact')) aria-current=\"page\" @endif>{{ __('Contact') }}</a>
            </nav>

            <div class="ml-auto order-1 md:order-none flex flex-wrap items-center gap-2">
                <x-locale-switcher />
                <x-theme-toggle />
                <x-notification-bell />
                @auth
                    @if (setting('catalog.price_currency', 'ks') === 'points' && \App\Services\PointService::systemOn())
                        <a href="{{ route('account.points') }}" class="btn hidden sm:inline-flex bg-point-soft text-point border-transparent font-bold hover:text-point"><x-icon name="star" size="16" />{{ number_format(auth()->user()->points) }} {{ __('pts') }}</a>
                    @else
                        <a href="{{ route('account.dashboard') }}" class="btn bg-ok-soft text-ok border-transparent font-bold hover:text-ok"><x-icon name="wallet" size="16" />{{ money(auth()->user()->balance) }}</a>
                    @endif
                    <a href="{{ route('account.profile') }}" class="no-underline" aria-label="{{ __('My profile') }}"><x-avatar :user="auth()->user()" :size="44" /></a>
                @else
                    <a href="{{ route('login') }}" class="btn">{{ __('Login') }}</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">{{ __('Register') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main" class="flex-1">
        @if (session('success'))
            <div class="max-w-7xl mx-auto px-4 sm:px-8 pt-6"><div class="badge-success rounded-xl px-4 py-3 text-sm font-medium">{{ __(session('success')) }}</div></div>
        @endif
        @if (session('error'))
            <div class="max-w-7xl mx-auto px-4 sm:px-8 pt-6"><div class="badge-rejected rounded-xl px-4 py-3 text-sm font-medium">{{ __(session('error')) }}</div></div>
        @endif

        @if (isset($shop) && $shop->about && request()->routeIs('home'))
            <div class="max-w-7xl mx-auto px-4 sm:px-8 pt-6"><div class="card p-5 text-sm leading-relaxed whitespace-pre-line">{{ $shop->about }}</div></div>
        @endif
        @yield('content')
    </main>

    @php $homePopup = \App\Support\Popup::home(); @endphp
    @if (\App\Support\Popup::homeVisible($homePopup))
        <x-popup :popup="$homePopup" id="welcome-popup" />
    @endif

    <footer class="mt-12 bg-surface border-t border-line">
        <div class="max-w-7xl mx-auto px-4 sm:px-8 py-10 flex flex-wrap gap-10">
            <div class="flex-[1_1_280px] flex flex-col gap-2.5">
                <span class="font-display text-xl font-bold">{{ setting('site.name', config('app.name')) }}</span>
                <p class="text-sm text-muted leading-relaxed m-0">{{ __('Check IMEI and model carefully — wrong entries are non-refundable. Orders already In Process cannot be cancelled.') }}</p>
            </div>
            <div class="flex-[1_1_160px] flex flex-col gap-2.5 text-sm"><span class="font-bold">{{ __('Support') }}</span><a href="{{ route('kb.index') }}">{{ __('Knowledge Base') }}</a><a href="{{ route('track') }}">{{ __('Track Order') }}</a><a href="{{ route('downloads') }}">{{ __('Downloads') }}</a><a href="{{ route('contact') }}">{{ __('Contact Us') }}</a></div>
            <div class="flex-[1_1_160px] flex flex-col gap-2.5 text-sm"><span class="font-bold">{{ __('Company') }}</span><a href="{{ route('blog.index') }}">{{ __('Blog') }}</a><a href="{{ route('testimonials') }}">{{ __('Testimonials') }}</a><a href="{{ route('rss') }}">RSS</a>@if ($terms = setting('pages.terms_slug'))<a href="{{ route('kb.show', $terms) }}">{{ __('Terms & Refunds') }}</a>@endif</div>
            <div class="flex-[1_1_160px] flex flex-col gap-2.5 text-sm"><span class="font-bold">{{ __('Channels') }}</span>
                @foreach (['telegram' => 'Telegram', 'viber' => 'Viber', 'facebook' => 'Facebook', 'youtube' => 'YouTube'] as $k => $label)
                    @if ($url = setting('social.'.$k)) <a href="{{ $url }}" rel="noopener" target="_blank">{{ $label }}</a> @endif
                @endforeach
                @if (setting('seo.analytics_enabled') === '1' && preg_match('/^G-[A-Z0-9]{4,20}$/i', (string) setting('seo.google_analytics_id')))
                    <button type="button" class="text-left" data-cookie-open>{{ __('Cookie settings') }}</button>
                @endif
            </div>
        </div>
    </footer>
    <x-cookie-consent />
</body>
</html>
