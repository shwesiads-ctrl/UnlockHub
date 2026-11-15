<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => __('Site not available')])
    <meta name="robots" content="noindex">
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <main class="card p-8 max-w-md w-full flex flex-col gap-4 text-center">
        <span class="mx-auto w-14 h-14 rounded-2xl bg-warn-soft text-warn flex items-center justify-center"><x-icon name="lock" size="28" /></span>
        <h1 class="m-0 text-2xl font-bold">{{ __('Site not available') }}</h1>
        <p class="m-0 text-muted">{{ __('This site is not activated right now. Please try again later.') }}</p>
        <p class="m-0 text-sm text-muted">{{ __('Site owner? Log in to activate the license.') }}</p>
        <a href="{{ route('login') }}" class="btn btn-primary self-center">{{ __('Log in') }}</a>
    </main>
</body>
</html>
