@extends('layouts.app', ['title' => 'Login'])

@section('content')
<x-auth-card title="{{ __('Welcome back') }}" subtitle="{{ __('Log in with your username or email.') }}">
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <x-login-wait />
        <div>
            <label for="login" class="label">{{ __('Username or email') }}</label>
            <input id="login" name="login" type="text" value="{{ old('login') }}" class="input" autocomplete="username" required autofocus @error('login') aria-invalid="true" aria-describedby="login-error" @enderror>
            <x-field-error name="login" />
        </div>
        <div>
            <div class="flex justify-between items-baseline">
                <label for="password" class="label">{{ __('Password') }}</label>
                <a href="{{ route('password.request') }}" class="text-[13px] font-semibold">{{ __('Forgot password?') }}</a>
            </div>
            <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
            <x-field-error name="password" />
        </div>
        <label class="flex items-center gap-2.5 text-sm text-muted">
            <input type="checkbox" name="remember" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('remember'))>
            {{ __('Keep me signed in') }}
        </label>
        <x-captcha form="login" />
        <button class="btn btn-primary btn-lg w-full">{{ __('Log in') }}</button>
    </form>

    @include('auth._social')

    <x-slot:footer>{{ __('New here?') }} <a href="{{ route('register') }}" class="font-semibold">{{ __('Create an account') }}</a></x-slot:footer>
</x-auth-card>
@endsection
