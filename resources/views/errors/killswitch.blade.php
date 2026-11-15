<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Maintenance — {{ config('app.name') }}</title>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f1117;color:#e2e8f0;font-family:system-ui,sans-serif;padding:1.5rem}
  .card{max-width:420px;width:100%;background:#1a1d27;border:1px solid #2d3148;border-radius:16px;padding:2.5rem 2rem;text-align:center}
  .icon{font-size:3rem;margin-bottom:1rem}
  h1{font-size:1.4rem;font-weight:700;margin-bottom:.5rem}
  p{font-size:.9rem;color:#94a3b8;line-height:1.6;margin-bottom:1.5rem}
  a{display:inline-block;padding:.6rem 1.4rem;border-radius:8px;background:#3b4bdb;color:#fff;text-decoration:none;font-size:.85rem;transition:background .2s}
  a:hover{background:#2d3bcc}
  .credit{margin-top:2rem;font-size:.75rem;color:#475569}
  .credit a{background:none;padding:0;color:#6366f1;text-decoration:underline;display:inline}
  .credit a:hover{background:none;color:#818cf8}
</style>
</head>
<body>
<div class="card">
  <div class="icon">🔧</div>
  <h1>Under Maintenance</h1>
  <p>{{ config('app.name') }} is temporarily unavailable while we perform scheduled maintenance. Please check back shortly.</p>
  <a href="{{ route('login') }}">Admin Login</a>
  <div class="credit">
    Dev: <a href="https://t.me/kominit" target="_blank" rel="noopener">MyominAung</a> · <a href="https://t.me/kominit" target="_blank" rel="noopener">@kominit</a>
  </div>
</div>
</body>
</html>
