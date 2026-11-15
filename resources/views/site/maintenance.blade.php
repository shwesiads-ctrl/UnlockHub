<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => 'Maintenance'])
</head>
<body class="min-h-screen bg-bg text-ink">
    @php
        $supportEmail = setting('contact.email');
        $supportTelegram = setting('social.telegram');
        $supportPhone = setting('contact.phone');
        $phoneHref = $supportPhone ? preg_replace('/[^0-9+]/', '', $supportPhone) : null;
        $maintenanceEnds = setting('site.maintenance_ends_at');
        $maintenanceTimezone = (string) config('unlockhub.timezone', 'Asia/Yangon');
    @endphp

    <div class="min-h-screen flex flex-col">
        <header class="border-b border-line bg-surface/90">
            <div class="max-w-6xl mx-auto w-full px-4 sm:px-8 py-4 flex items-center justify-between gap-4">
                <a href="{{ url('/') }}" class="no-underline text-ink"><x-site-logo /></a>
                <span class="inline-flex items-center gap-2 rounded-full border border-warn/30 bg-warn-soft px-3 py-1.5 text-xs font-bold text-warn">
                    <span class="h-2 w-2 rounded-full bg-warn animate-pulse"></span>
                    {{ __('Maintenance in progress') }}
                </span>
            </div>
        </header>

        <main id="main" class="flex-1 flex items-center py-12 sm:py-16">
            <div class="max-w-6xl mx-auto w-full px-4 sm:px-8 grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,.8fr)] items-stretch">
                <section class="card p-7 sm:p-10 lg:p-12 flex flex-col justify-center gap-5">
                    <div class="h-14 w-14 rounded-2xl bg-brand-soft text-brand-ink grid place-items-center" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a5 5 0 0 0-6.4 6.4L3 18l3 3 5.3-5.3a5 5 0 0 0 6.4-6.4l-3.1 3.1-3-3 3.1-3.1Z"/><path d="m17 4 3 3"/></svg>
                    </div>
                    <div class="flex flex-col gap-3">
                        <span class="badge badge-pending self-start">{{ __('We’ll be back soon') }}</span>
                        <h1 class="m-0 text-3xl sm:text-4xl font-bold tracking-tight">{{ __('We’re making things better.') }}</h1>
                        <p class="m-0 max-w-2xl text-base sm:text-lg text-muted leading-relaxed">{{ $message }}</p>
                    </div>
                    @if ($maintenanceEnds)
                        <div class="mt-1 rounded-xl border border-line bg-surface-2 px-4 py-3 text-sm">
                            <span class="text-muted">{{ __('Expected back') }}:</span>
                            <strong class="ml-1">{{ \Illuminate\Support\Carbon::parse($maintenanceEnds, $maintenanceTimezone)->format('M j, Y · g:i A') }} {{ $maintenanceTimezone }}</strong>
                        </div>
                    @endif
                    <p class="m-0 text-sm text-muted">{{ __('Thank you for your patience while we complete scheduled maintenance.') }}</p>
                </section>

                <aside class="card p-6 sm:p-8 flex flex-col gap-5" aria-labelledby="maintenance-contact-title">
                    <div>
                        <span class="badge badge-info">{{ __('Contact Us') }}</span>
                        <h2 id="maintenance-contact-title" class="mt-3 mb-1 text-xl font-bold">{{ __('Need help?') }}</h2>
                        <p class="m-0 text-sm text-muted">{{ __('Reach our support team through any available channel.') }}</p>
                    </div>

                    <div class="flex flex-col gap-3">
                        @if ($supportEmail)
                            <a href="mailto:{{ $supportEmail }}" class="group flex items-center gap-3 rounded-xl border border-line bg-surface-2 p-3.5 no-underline text-ink hover:border-brand">
                                <span class="h-10 w-10 rounded-lg bg-brand-soft text-brand-ink grid place-items-center shrink-0" aria-hidden="true">✉</span>
                                <span class="min-w-0"><span class="block text-xs text-muted">{{ __('Email') }}</span><strong class="block truncate text-sm">{{ $supportEmail }}</strong></span>
                            </a>
                        @endif

                        @if ($supportTelegram)
                            <a href="{{ $supportTelegram }}" target="_blank" rel="noopener noreferrer" class="group flex items-center gap-3 rounded-xl border border-line bg-surface-2 p-3.5 no-underline text-ink hover:border-brand">
                                <span class="h-10 w-10 rounded-lg bg-brand-soft text-brand-ink grid place-items-center shrink-0" aria-hidden="true">➤</span>
                                <span class="min-w-0"><span class="block text-xs text-muted">{{ __('Telegram') }}</span><strong class="block truncate text-sm">{{ preg_replace('#^https?://#', '', $supportTelegram) }}</strong></span>
                                <span class="ml-auto text-muted" aria-hidden="true">↗</span>
                            </a>
                        @endif

                        @if ($supportPhone)
                            <a href="tel:{{ $phoneHref }}" class="group flex items-center gap-3 rounded-xl border border-line bg-surface-2 p-3.5 no-underline text-ink hover:border-brand">
                                <span class="h-10 w-10 rounded-lg bg-brand-soft text-brand-ink grid place-items-center shrink-0" aria-hidden="true">☎</span>
                                <span class="min-w-0"><span class="block text-xs text-muted">{{ __('Phone') }}</span><strong class="block truncate text-sm">{{ $supportPhone }}</strong></span>
                            </a>
                        @endif

                        @unless ($supportEmail || $supportTelegram || $supportPhone)
                            <p class="m-0 rounded-xl border border-line bg-surface-2 p-4 text-sm text-muted">{{ __('Support contact details are not available right now.') }}</p>
                        @endunless
                    </div>

                    @if (setting('site.support_hours'))
                        <p class="m-0 border-t border-line pt-4 text-xs text-muted">{{ __('Support hours') }}: {{ setting('site.support_hours') }}</p>
                    @endif
                </aside>
            </div>
        </main>

        <footer class="border-t border-line">
            <div class="max-w-6xl mx-auto w-full px-4 sm:px-8 py-5 flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
                <span>© {{ now($maintenanceTimezone)->year }} {{ setting('site.name', config('app.name')) }}</span>
                @guest <a href="{{ route('login') }}" class="no-underline">{{ __('Administrator sign in') }}</a> @endguest
            </div>
        </footer>
    </div>
</body>
</html>
