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

            <nav aria-label="Main" class="flex flex-wrap gap-1 sm:ml-3">
                <a href="{{ route('home') }}" @class(['nav-link', 'active' => request()->routeIs('home')])>{{ __('Home') }}</a>
                <a href="{{ route('services.index') }}" @class(['nav-link', 'active' => request()->routeIs('services.*')])>{{ __('Services & Pricing') }}</a>
                <a href="{{ route('track') }}" @class(['nav-link', 'active' => request()->routeIs('track')])>{{ __('Track Order') }}</a>
                <a href="{{ route('kb.index') }}" @class(['nav-link', 'active' => request()->routeIs('kb.*')])>{{ __('Knowledge Base') }}</a>
                <a href="{{ route('blog.index') }}" @class(['nav-link', 'active' => request()->routeIs('blog.*')])>{{ __('Blog') }}</a>
                <a href="{{ route('contact') }}" @class(['nav-link', 'active' => request()->routeIs('contact')])>{{ __('Contact') }}</a>
            </nav>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                @php $other = app()->getLocale() === 'my' ? 'en' : 'my'; @endphp
                <a href="{{ route('locale', $other) }}" class="btn text-[13px]" lang="{{ $other }}" aria-label="{{ $other === 'my' ? 'Switch to Myanmar' : 'Switch to English' }}">{{ $other === 'my' ? 'မြန်မာ' : 'English' }}</a>
                <x-theme-toggle />
                <x-notification-bell />
                @auth
                    <a href="{{ route('account.dashboard') }}" class="btn bg-ok-soft text-ok border-transparent font-bold hover:text-ok"><x-icon name="wallet" size="16" />{{ money(auth()->user()->balance) }}</a>
                    @if (\App\Services\PointService::systemOn())
                        <a href="{{ route('account.points') }}" class="btn bg-point-soft text-point border-transparent font-bold hover:text-point"><x-icon name="star" size="16" />{{ number_format(auth()->user()->points) }} pts</a>
                    @endif
                    <a href="{{ route('account.profile') }}" class="no-underline" aria-label="My profile"><x-avatar :user="auth()->user()" :size="44" /></a>
                @else
                    <a href="{{ route('login') }}" class="btn">{{ __('Login') }}</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">{{ __('Register') }}</a>
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
                <p class="text-sm text-muted leading-relaxed m-0">{{ __('Check IMEI and model carefully — wrong entries are non-refundable. Orders already In Process cannot be cancelled.') }}</p>
            </div>
            <div class="flex-[1_1_160px] flex flex-col gap-2.5 text-sm"><span class="font-bold">{{ __('Support') }}</span><a href="{{ route('kb.index') }}">{{ __('Knowledge Base') }}</a><a href="{{ route('track') }}">{{ __('Track Order') }}</a><a href="{{ route('downloads') }}">{{ __('Downloads') }}</a><a href="{{ route('contact') }}">{{ __('Contact Us') }}</a></div>
            <div class="flex-[1_1_160px] flex flex-col gap-2.5 text-sm"><span class="font-bold">{{ __('Company') }}</span><a href="{{ route('blog.index') }}">{{ __('Blog') }}</a><a href="{{ route('testimonials') }}">{{ __('Testimonials') }}</a><a href="{{ route('rss') }}">RSS</a>@if ($terms = setting('pages.terms_slug'))<a href="{{ route('kb.show', $terms) }}">{{ __('Terms & Refunds') }}</a>@endif</div>
            <div class="flex-[1_1_160px] flex flex-col gap-2.5 text-sm"><span class="font-bold">{{ __('Channels') }}</span>
                @foreach (['telegram' => 'Telegram', 'viber' => 'Viber', 'facebook' => 'Facebook', 'youtube' => 'YouTube'] as $k => $label)
                    @if ($url = setting('social.'.$k)) <a href="{{ $url }}" rel="noopener" target="_blank">{{ $label }}</a> @endif
                @endforeach
            </div>
        </div>
    </footer>
</body>
</html>
