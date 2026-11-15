@extends('layouts.app', ['title' => 'Register'])

@section('content')
<x-auth-card title="Create your account" subtitle="Reseller pricing, wallet top-ups and order tracking in one place.">
    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <div>
            <label for="name" class="label">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" class="input" autocomplete="name" required autofocus>
            <x-field-error name="name" />
        </div>
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2">
            <div>
                <label for="username" class="label">Username</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}" class="input" autocomplete="username" required>
                <x-field-error name="username" />
            </div>
            <div>
                <label for="phone" class="label">Phone <span class="font-normal text-muted">(optional)</span></label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" class="input" placeholder="09…" autocomplete="tel">
                <x-field-error name="phone" />
            </div>
        </div>
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" autocomplete="email" required>
            <x-field-error name="email" />
        </div>
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2">
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" class="input" autocomplete="new-password" required>
                <x-field-error name="password" />
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
            </div>
        </div>
        <p class="m-0 -mt-2 text-[13px] text-muted">At least 8 characters with letters and numbers.</p>
        <div>
            <label for="referral_code" class="label">Referral code <span class="font-normal text-muted">(optional)</span></label>
            <input id="referral_code" name="referral_code" type="text" value="{{ old('referral_code', $ref) }}" class="input uppercase">
            <x-field-error name="referral_code" />
        </div>
        <label class="flex items-start gap-2.5 text-sm text-muted leading-normal">
            <input type="checkbox" name="terms" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(old('terms'))>
            <span>I agree to the terms. I understand wrong IMEI/model entries are non-refundable and In Process orders can't be cancelled.</span>
        </label>
        <x-field-error name="terms" />
        <button class="btn btn-primary btn-lg w-full">Create account</button>
    </form>

    <x-slot:footer>Already have an account? <a href="{{ route('login') }}" class="font-semibold">Log in</a></x-slot:footer>
</x-auth-card>
@endsection
