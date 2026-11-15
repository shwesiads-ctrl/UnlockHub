<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
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
