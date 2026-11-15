@extends('layouts.app', ['title' => $linking ? 'Connect Telegram' : 'Telegram login'])

@section('content')
<section class="max-w-lg mx-auto my-12 card p-6 flex flex-col gap-5">
    <h1 class="m-0 text-2xl font-bold">{{ $linking ? 'Connect your Telegram account' : 'Continue with Telegram' }}</h1>
    <p class="m-0 text-sm text-muted">{{ $linking ? 'Confirm the Telegram account you want to connect to your current UnlockHub account.' : 'Confirm your Telegram account to sign in.' }} This request expires in 10 minutes.</p>
    <div class="flex justify-center min-h-[44px]">
        <script async src="https://telegram.org/js/telegram-widget.js?22"
                data-telegram-login="{{ \App\Services\Social\SocialProviders::telegramBotUsername() }}"
                data-size="large" data-radius="10"
                data-auth-url="{{ route('social.telegram', ['state' => $state]) }}"></script>
    </div>
    <a href="{{ route($linking ? 'account.security' : 'login') }}" class="btn">Cancel</a>
</section>
@endsection
