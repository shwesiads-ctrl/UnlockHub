@php
    $cookieAnalyticsId = setting('seo.google_analytics_id');
    $cookieAnalyticsEnabled = setting('seo.analytics_enabled') === '1'
        && is_string($cookieAnalyticsId)
        && preg_match('/^G-[A-Z0-9]{4,20}$/i', $cookieAnalyticsId)
        && ! request()->is('admin', 'admin/*');
@endphp
@if ($cookieAnalyticsEnabled)
<section id="cookie-consent" class="fixed z-[100] inset-x-4 bottom-4 sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-[min(100%-3rem,480px)] card p-5 shadow-2xl" role="region" aria-label="{{ __('Cookie preferences') }}" hidden>
    <h2 class="m-0 text-lg font-bold">{{ __('Cookie preferences') }}</h2>
    <p class="m-0 mt-2 text-sm text-muted leading-relaxed">{{ __('We use essential cookies to keep UnlockHub working. If you choose OK, Google Analytics will use optional cookies to measure visits. Cancel rejects analytics cookies.') }}</p>
    <div class="flex flex-wrap justify-end gap-2 mt-4">
        <button type="button" class="btn" data-cookie-reject>{{ __('Cancel') }}</button>
        <button type="button" class="btn btn-primary" data-cookie-accept>{{ __('OK') }}</button>
    </div>
</section>
<script>
(function () {
    'use strict';
    var banner = document.getElementById('cookie-consent');
    if (!banner) return;
    var cookieName = 'uh_cookie_consent';
    var analyticsId = @json($cookieAnalyticsId);
    var analyticsLoaded = false;

    function choice() {
        var item = document.cookie.split('; ').find(function (part) { return part.indexOf(cookieName + '=') === 0; });
        return item ? decodeURIComponent(item.slice(cookieName.length + 1)) : '';
    }

    function save(value) {
        document.cookie = cookieName + '=' + encodeURIComponent(value) + '; Max-Age=15552000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }

    function clearAnalyticsCookies() {
        document.cookie.split(';').forEach(function (part) {
            var name = part.split('=')[0].trim();
            if (/^_(ga|gid|gat)/.test(name)) {
                document.cookie = name + '=; Max-Age=0; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            }
        });
    }

    function loadAnalytics() {
        if (typeof analyticsId !== 'string') return;
        if (analyticsLoaded) {
            if (window.gtag) window.gtag('consent', 'update', { analytics_storage: 'granted' });
            return;
        }
        analyticsLoaded = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('consent', 'default', { analytics_storage: 'denied' });
        window.gtag('consent', 'update', { analytics_storage: 'granted' });
        window.gtag('js', new Date());
        window.gtag('config', analyticsId);
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(analyticsId);
        document.head.appendChild(script);
    }

    function show() { banner.hidden = false; }
    function hide() { banner.hidden = true; }

    document.querySelectorAll('[data-cookie-open]').forEach(function (button) {
        button.addEventListener('click', show);
    });
    banner.querySelector('[data-cookie-accept]').addEventListener('click', function () {
        save('accepted');
        hide();
        loadAnalytics();
    });
    banner.querySelector('[data-cookie-reject]').addEventListener('click', function () {
        save('rejected');
        if (window.gtag) window.gtag('consent', 'update', { analytics_storage: 'denied' });
        clearAnalyticsCookies();
        hide();
    });

    var savedChoice = choice();
    if (savedChoice === 'accepted') loadAnalytics();
    else if (savedChoice !== 'rejected') show();
})();
</script>
@endif
