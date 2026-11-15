@extends('layouts.app', ['title' => 'Login'])

@section('content')
<x-auth-card title="Welcome back" subtitle="Log in with your username or email.">
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <div>
            <label for="login" class="label">Username or email</label>
            <input id="login" name="login" type="text" value="{{ old('login') }}" class="input" autocomplete="username" required autofocus @error('login') aria-invalid="true" aria-describedby="login-error" @enderror>
            <x-field-error name="login" />
        </div>
        <div>
            <div class="flex justify-between items-baseline">
                <label for="password" class="label">Password</label>
                <a href="{{ route('password.request') }}" class="text-[13px] font-semibold">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
            <x-field-error name="password" />
        </div>
        <label class="flex items-center gap-2.5 text-sm text-muted">
            <input type="checkbox" name="remember" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('remember'))>
            Keep me signed in
        </label>
        <button class="btn btn-primary btn-lg w-full">Log in</button>
    </form>

    <x-slot:footer>New here? <a href="{{ route('register') }}" class="font-semibold">Create an account</a></x-slot:footer>
</x-auth-card>
@endsection
