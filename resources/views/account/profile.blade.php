@extends('layouts.account', ['title' => 'My Profile'])

@section('content')
{{-- Header card --}}
<section class="card overflow-hidden">
    <div class="h-28 bg-brand" aria-hidden="true"></div>
    <div class="px-6 pb-6 -mt-12 flex flex-wrap gap-5 items-end">
        <div class="relative">
            <x-avatar :user="$user" :size="104" class="ring-4 ring-[var(--uh-surface)]" />
            <form method="POST" action="{{ route('account.profile.avatar') }}" enctype="multipart/form-data" class="absolute -bottom-1 -right-1" data-auto-submit>
                @csrf
                <label for="avatar" class="w-9 h-9 rounded-full bg-surface border border-line text-ink flex items-center justify-center cursor-pointer hover:bg-surface-2" title="{{ __('Change photo') }}">
                    <x-icon name="upload" size="16" /><span class="sr-only">{{ __('Change photo') }}</span>
                </label>
                <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only">
            </form>
        </div>
        <div class="flex-1 min-w-[220px] flex flex-col gap-1 pb-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="m-0 text-2xl font-bold">{{ $user->name }}</h2>
                <x-user-badges :user="$user" />
                @if ($user->hasTwoFactor()) <span class="badge badge-success">2FA</span> @endif
            </div>
            <span class="text-sm text-muted">{{ '@'.$user->username }}@if ($user->shop_name) · {{ $user->shop_name }}@endif @if ($user->city) · {{ $user->city }}@endif</span>
            <span class="text-xs text-muted">Member since {{ $user->created_at->format('M Y') }}</span>
        </div>
        @if ($user->avatar_path)
            <form method="POST" action="{{ route('account.profile.avatar.remove') }}" class="pb-1">@csrf @method('DELETE')<button class="btn h-9 text-xs">{{ __('Remove photo') }}</button></form>
        @endif
    </div>
    <x-field-error name="avatar" />

    <div class="grid grid-cols-2 sm:grid-cols-5 border-t border-line">
        @foreach ([
            [money($user->balance), 'Balance'],
            [number_format($user->points), 'Points'],
            [number_format($stats['orders']), 'Orders'],
            [number_format($stats['success']), 'Completed'],
            [number_format($stats['referrals']), 'Friends invited'],
        ] as [$value, $label])
            <div class="px-5 py-4 flex flex-col gap-0.5 border-line [&:not(:last-child)]:border-r">
                <span class="font-display text-xl font-bold">{{ $value }}</span>
                <span class="text-xs text-muted">{{ __($label) }}</span>
            </div>
        @endforeach
    </div>
</section>

<div class="flex flex-wrap gap-5 items-start">
    <form method="POST" action="{{ route('account.profile.update') }}" class="flex-[999_1_520px] min-w-0 card p-6 flex flex-col gap-4">
        @csrf @method('PUT')
        <h2 class="m-0 text-lg font-bold">{{ __('Details') }}</h2>
        <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
            <div>
                <label for="name" class="label">{{ __('Full name') }}</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" class="input" required>
                <x-field-error name="name" />
            </div>
            <div>
                <label for="shop_name" class="label">{{ __('Shop name') }}</label>
                <input id="shop_name" name="shop_name" value="{{ old('shop_name', $user->shop_name) }}" class="input" placeholder="e.g. Golden Mobile Repair">
                <x-field-error name="shop_name" />
            </div>
            <div>
                <label for="phone" class="label">{{ __('Phone') }}</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" class="input" placeholder="09…">
                <x-field-error name="phone" />
            </div>
            <div>
                <label for="city" class="label">{{ __('City') }}</label>
                <input id="city" name="city" value="{{ old('city', $user->city) }}" class="input" placeholder="{{ __('Yangon, Mandalay…') }}">
                <x-field-error name="city" />
            </div>
            <div>
                <label for="email" class="label">Email @unless ($user->email) <span class="text-danger">*</span> @endunless</label>
                @if ($user->email)
                    <input id="email" type="email" value="{{ $user->email }}" class="input opacity-70" disabled>
                    <p class="m-0 mt-1 text-xs text-muted">{{ __('Contact support to change your email.') }}</p>
                @else
                    <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required>
                    <p class="m-0 mt-1 text-xs text-warn">{{ __('Needed for order results, receipts and password reset.') }}</p>
                @endif
                <x-field-error name="email" />
            </div>
            <div>
                <label class="label">{{ __('Username') }}</label>
                <input type="text" value="{{ $user->username }}" class="input opacity-70" disabled>
            </div>
            <div>
                <label for="preferred_locale" class="label">{{ __('Language') }}</label>
                <select id="preferred_locale" name="preferred_locale" class="input">
                    @foreach (config('unlockhub.locales') as $code => $label)
                        <option value="{{ $code }}" @selected($user->preferred_locale === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="country" class="label">{{ __('Country') }} <span class="font-normal text-muted text-xs">· {{ __('decides which payment methods you see') }}</span></label>
                <select id="country" name="country" class="input">
                    <option value="">{{ __('Auto (from language)') }}</option>
                    @foreach (config('unlockhub.countries') as $code => $label)
                        <option value="{{ $code }}" @selected($user->country === $code)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="preferred_currency" class="label">{{ __('Show prices in') }}</label>
                <select id="preferred_currency" name="preferred_currency" class="input">
                    @foreach (config('unlockhub.currencies') as $c)
                        <option value="{{ $c }}" @selected($user->preferred_currency === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="flex items-start gap-2.5 text-sm">
            <input type="checkbox" name="notify_email" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(old('notify_email', $user->notify_email))>
            <span><strong>{{ __('Email me') }}</strong> {{ __('when an order finishes or a top-up is reviewed.') }}</span>
        </label>
        <button class="btn btn-primary self-start">{{ __('Save profile') }}</button>
    </form>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">
        <section class="card p-5 flex flex-col gap-3">
            <div class="flex justify-between items-center">
                <h2 class="m-0 text-lg font-bold">Profile {{ $user->profilePercent() }}%</h2>
                @if ($bonus > 0)
                    <span @class(['badge', 'badge-success' => $user->profile_bonus_awarded, 'badge-pending' => ! $user->profile_bonus_awarded])>{{ $user->profile_bonus_awarded ? 'Bonus received' : '+'.$bonus.' pts' }}</span>
                @endif
            </div>
            <div class="h-2 rounded-full bg-surface-2 overflow-hidden" role="progressbar" aria-valuenow="{{ $user->profilePercent() }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Profile completion') }}">
                <div class="h-full bg-ok" style="width: {{ $user->profilePercent() }}%"></div>
            </div>
            <ul class="m-0 p-0 list-none flex flex-col gap-1.5 text-sm">
                @foreach (['email' => 'Email', 'phone' => 'Phone', 'avatar' => 'Profile photo', 'shop_name' => 'Shop name', 'city' => 'City'] as $key => $label)
                    <li class="flex items-center gap-2 {{ $checklist[$key] ? 'text-ok' : 'text-muted' }}">
                        <x-icon :name="$checklist[$key] ? 'check' : 'x'" size="16" />{{ $label }}
                    </li>
                @endforeach
            </ul>
            @if ($bonus > 0 && ! $user->profile_bonus_awarded)
                <p class="m-0 text-xs text-muted">Fill everything to get {{ $bonus }} points.</p>
            @endif
        </section>

        <section class="card p-5 flex flex-col gap-2 text-sm">
            <h2 class="m-0 text-lg font-bold">{{ __('Account') }}</h2>
            <div class="flex justify-between"><span class="text-muted">{{ __('Referral code') }}</span><span class="font-mono font-semibold">{{ $user->referral_code }}</span></div>
            <div class="flex justify-between"><span class="text-muted">{{ __('Connected logins') }}</span><span>{{ $user->socialAccounts->count() ?: '—' }}</span></div>
            <div class="flex justify-between"><span class="text-muted">{{ __('Last login') }}</span><span>{{ $user->last_login_at?->diffForHumans() ?? '—' }}</span></div>
            <a href="{{ route('account.security') }}" class="btn mt-2">Password, 2FA &amp; logins</a>
        </section>
    </aside>
</div>
@endsection
