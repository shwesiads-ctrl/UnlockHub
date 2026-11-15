@extends('layouts.app', ['title' => 'Forgot password'])

@section('content')
<x-auth-card title="{{ __('Reset your password') }}" subtitle="{{ __('Enter your account email and we\'ll send you a reset link.') }}">
    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <div>
            <label for="email" class="label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" autocomplete="email" required autofocus>
            <x-field-error name="email" />
        </div>
        <x-captcha form="password" />
        <button class="btn btn-primary btn-lg w-full">{{ __('Send reset link') }}</button>
    </form>
    <x-slot:footer><a href="{{ route('login') }}">{{ __('← Back to login') }}</a></x-slot:footer>
</x-auth-card>
@endsection
