<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ setting('pwa.theme_color', '#2459E8') }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0D1220">
@php
    $seoTitle = setting('seo.title') ?: setting('site.name', config('app.name'));
    $seoDescription = setting('seo.description') ?: (setting('site.tagline') ?: __('Unlock codes, tool credits & activations — in one wallet.'));
    $seoImage = setting('seo.image_url');
    $analyticsId = setting('seo.google_analytics_id');
    $analyticsOn = setting('seo.analytics_enabled') === '1'
        && is_string($analyticsId)
        && preg_match('/^G-[A-Z0-9]{4,20}$/i', $analyticsId)
        && ! request()->is('admin', 'admin/*');
@endphp
<meta name="description" content="{{ $seoDescription }}">
@if (filled(setting('seo.keywords')))<meta name="keywords" content="{{ setting('seo.keywords') }}">@endif
<meta property="og:type" content="website">
<meta property="og:title" content="{{ isset($title) ? __($title).' · '.setting('site.name', config('app.name')) : $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($seoImage)<meta property="og:image" content="{{ $seoImage }}">@endif
<meta name="twitter:card" content="{{ $seoImage ? 'summary_large_image' : 'summary' }}">
@if ($seoImage)<meta name="twitter:image" content="{{ $seoImage }}">@endif
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<link rel="apple-touch-icon" href="{{ setting('pwa.icon_version') ? Storage::disk('public')->url('pwa/apple-touch-icon.png').'?v='.setting('pwa.icon_version') : asset('icons/apple-touch-icon.png') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ setting('pwa.short_name', \Illuminate\Support\Str::limit(setting('site.name', config('app.name')), 12, '')) }}">
@php
    $swBuild = @filemtime(public_path('build/manifest.json')) ?: 0;
@endphp
<meta name="sw-version" content="{{ substr(md5($swBuild.'|'.setting('pwa.icon_version').'|2'), 0, 10) }}">
@auth <meta name="push-key" content="{{ \App\Services\WebPush::enabled() ? \App\Services\WebPush::publicKey() : '' }}"> @endauth
<title>{{ isset($title) ? __($title).' · '.setting('site.name', config('app.name')) : $seoTitle }}</title>
@if ($analyticsOn)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsId }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', @json($analyticsId));
</script>
@endif
<script>
  (function () {
    try {
      var t = localStorage.getItem('uh-theme');
      if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
      }
    } catch (e) {}
  })();
</script>
@if ($favicon = setting('site.favicon'))
<link rel="icon" href="{{ Storage::disk('public')->url($favicon) }}">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
@php
    // Only the script font for the current language is loaded, so pages stay light.
    $scriptFont = [
        'my' => '&family=Noto+Sans+Myanmar:wght@400;600;700',
        'th' => '&family=Noto+Sans+Thai:wght@400;600;700',
        'lo' => '&family=Noto+Sans+Lao:wght@400;600;700',
        'zh' => '&family=Noto+Sans+SC:wght@400;600;700',
    ][app()->getLocale()] ?? '';
@endphp
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700{!! $scriptFont !!}&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@if (isset($shop) && $shop->brand_color && preg_match('/^#[0-9a-fA-F]{6}$/', $shop->brand_color))
<style>:root, .dark { --uh-brand: {{ $shop->brand_color }}; --uh-brand-ink: {{ $shop->brand_color }}; }</style>
@endif
