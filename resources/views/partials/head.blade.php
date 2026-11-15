<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ setting('pwa.theme_color', '#2459E8') }}">
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
<title>{{ isset($title) ? $title.' · ' : '' }}{{ setting('site.name', config('app.name')) }}</title>
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Myanmar:wght@400;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@if (isset($shop) && $shop->brand_color && preg_match('/^#[0-9a-fA-F]{6}$/', $shop->brand_color))
<style>:root, .dark { --uh-brand: {{ $shop->brand_color }}; --uh-brand-ink: {{ $shop->brand_color }}; }</style>
@endif
