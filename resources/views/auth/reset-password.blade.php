@extends('layouts.app', ['title' => 'New password'])

@section('content')
<x-auth-card title="{{ __('Choose a new password') }}" subtitle="{{ __('Trusted devices will be signed out.') }}">
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="input" autocomplete="email" required>
            <x-field-error name="email" />
        </div>
        <div>
            <label for="password" class="label">{{ __('New password') }}</label>
            <input id="password" name="password" type="password" class="input" autocomplete="new-password" required autofocus>
            <x-field-error name="password" />
        </div>
        <div>
            <label for="password_confirmation" class="label">{{ __('Confirm new password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary btn-lg w-full">{{ __('Save password') }}</button>
    </form>
</x-auth-card>
@endsection
