@extends('layouts.app', ['title' => 'Verify'])

@section('content')
<x-auth-card
    :title="$method === 'totp' ? 'Authenticator code' : 'Check your email'"
    :subtitle="$method === 'totp'
        ? 'Open Google Authenticator and enter the 6-digit code for this site.'
        : 'We sent a 6-digit code to '.$maskedEmail.'. It expires in '.config('unlockhub.otp.ttl_minutes').' minutes.'">

    <form method="POST" action="{{ route('login.challenge') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <div>
            <label for="code" class="label">Code</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="10"
                   class="input text-center font-mono text-2xl tracking-[0.4em] h-14" required autofocus
                   @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            <x-field-error name="code" />
        </div>
        <label class="flex items-start gap-2.5 text-sm text-muted leading-normal">
            <input type="checkbox" name="trust_device" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]">
            <span><strong class="text-ink">Trust this device?</strong> Skip this step on this browser for {{ config('unlockhub.otp.trusted_device_days') }} days. Don't tick this on a shared computer.</span>
        </label>
        <button class="btn btn-primary btn-lg w-full">Verify &amp; continue</button>
    </form>

    @if ($method === 'email')
        <form method="POST" action="{{ route('login.challenge.resend') }}" class="text-center">
            @csrf
            <button class="btn border-transparent bg-transparent text-brand-ink" @disabled($cooldown > 0)>
                {{ $cooldown > 0 ? "Resend code in {$cooldown}s" : 'Resend code' }}
            </button>
        </form>
    @else
        <p class="m-0 text-[13px] text-muted text-center">Lost your phone? Contact support to reset Google Authenticator.</p>
    @endif

    <x-slot:footer><a href="{{ route('login') }}">← Use a different account</a></x-slot:footer>
</x-auth-card>
@endsection
