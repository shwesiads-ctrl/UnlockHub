@extends('layouts.account', ['title' => 'Settings & Security'])

@section('content')
<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_520px] min-w-0 flex flex-col gap-5">

        <div class="rounded-xl px-4 py-3 text-sm bg-brand-soft text-brand-ink">{{ __('Name, photo, shop details and language are on') }} <a href="{{ route('account.profile') }}" class="font-semibold">{{ __('My Profile') }}</a>.</div>

        {{-- Password --}}
        <section class="card p-6 flex flex-col gap-4">
            <h2 class="m-0 text-lg font-bold">{{ $user->password_set ? 'Change password' : 'Set a password' }}</h2>
            @unless ($user->password_set)
                <p class="m-0 text-sm text-muted">{{ __('You signed up with a social login. Set a password to also log in with your username.') }}</p>
            @endunless
            <form method="POST" action="{{ route('account.security.password') }}" class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
                @csrf @method('PUT')
                @if ($user->password_set)
                <div>
                    <label for="current_password" class="label">{{ __('Current password') }}</label>
                    <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password" required>
                    <x-field-error name="current_password" />
                </div>
                @endif
                <div>
                    <label for="password" class="label">{{ __('New password') }}</label>
                    <input id="password" name="password" type="password" class="input" autocomplete="new-password" required>
                    <x-field-error name="password" />
                </div>
                <div>
                    <label for="password_confirmation" class="label">{{ __('Confirm') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
                </div>
                <div class="col-span-full"><button class="btn btn-primary">{{ $user->password_set ? 'Change password' : 'Set password' }}</button></div>
            </form>
        </section>
    </div>

    <div class="flex-[1_1_340px] min-w-0 flex flex-col gap-5">

        {{-- Google Authenticator --}}
        <section class="card p-6 flex flex-col gap-4">
            <div class="flex justify-between items-center gap-3">
                <h2 class="m-0 text-lg font-bold">{{ __('Google Authenticator') }}</h2>
                @if ($user->hasTwoFactor())
                    <span class="badge badge-success">{{ __('On') }}</span>
                @else
                    <span class="badge badge-pending">{{ __('Off') }}</span>
                @endif
            </div>

            @if ($user->hasTwoFactor())
                <p class="m-0 text-sm text-muted leading-relaxed">{{ __('You\'ll be asked for a 6-digit code from the app when you log in on a new device.') }}</p>
                <form method="POST" action="{{ route('account.security.2fa.disable') }}" class="flex flex-col gap-3">
                    @csrf @method('DELETE')
                    <div>
                        <label for="disable_password" class="label">{{ __('Password to turn off') }}</label>
                        <input id="disable_password" name="password" type="password" class="input h-11" required>
                        @error('password', 'twoFactor') <p class="mt-1.5 mb-0 text-[13px] font-medium text-danger">{{ $message }}</p> @enderror
                    </div>
                    <button class="btn btn-danger">{{ __('Turn off') }}</button>
                </form>
            @elseif ($qr)
                <p class="m-0 text-sm text-muted leading-relaxed">{{ __('1. Scan this QR code with Google Authenticator.') }}<br>{{ __('2. Enter the 6-digit code it shows.') }}</p>
                <div class="self-center p-3 rounded-xl bg-white" role="img" aria-label="{{ __('QR code for Google Authenticator') }}">{!! $qr !!}</div>
                <p class="m-0 text-xs text-muted break-all">{{ __('Can\'t scan? Enter this key:') }} <span class="font-mono text-ink">{{ $user->two_factor_secret }}</span></p>
                <form method="POST" action="{{ route('account.security.2fa.confirm') }}" class="flex flex-col gap-3">
                    @csrf
                    <div>
                        <label for="code" class="label">{{ __('6-digit code') }}</label>
                        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="input text-center font-mono text-xl tracking-[0.3em]" required>
                        <x-field-error name="code" />
                    </div>
                    <button class="btn btn-primary">Confirm &amp; turn on</button>
                </form>
            @else
                <p class="m-0 text-sm text-muted leading-relaxed">{{ __('Without it, we email you a code when you log in on a new device. The app is faster and works offline.') }}</p>
                <form method="POST" action="{{ route('account.security.2fa.start') }}">
                    @csrf
                    <button class="btn btn-primary w-full">{{ __('Set up Google Authenticator') }}</button>
                </form>
            @endif
        </section>

        {{-- Connected accounts --}}
        @php $providers = \App\Services\Social\SocialProviders::enabled(); $linked = $user->socialAccounts->keyBy('provider'); @endphp
        @if ($providers || $linked->isNotEmpty())
            <section class="card p-6 flex flex-col gap-3">
                <h2 class="m-0 text-lg font-bold">{{ __('Connected logins') }}</h2>
                <p class="m-0 text-sm text-muted">{{ __('Log in with these instead of your password.') }}</p>
                @foreach (array_unique(array_merge($providers, $linked->keys()->all())) as $p)
                    @php $acc = $linked[$p] ?? null; $label = \App\Services\Social\SocialProviders::PROVIDERS[$p]['label'] ?? ucfirst($p); @endphp
                    <div class="flex items-center gap-3 p-3 rounded-[10px] bg-surface-2">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold">{{ $label }}</div>
                            <div class="text-xs text-muted truncate">{{ $acc ? ($acc->name ?: $acc->email ?: 'Connected') : 'Not connected' }}</div>
                        </div>
                        @if ($acc)
                            <form method="POST" action="{{ route('social.unlink', $p) }}">@csrf @method('DELETE')<button class="btn h-9 text-xs">{{ __('Disconnect') }}</button></form>
                        @elseif ($p !== 'telegram' && in_array($p, $providers, true))
                            <a href="{{ route('social.redirect', $p) }}" class="btn h-9 text-xs">{{ __('Connect') }}</a>
                        @endif
                    </div>
                @endforeach
                @if (in_array('telegram', $providers, true) && ! isset($linked['telegram']))
                    <div class="flex justify-center"><script async src="https://telegram.org/js/telegram-widget.js?22" data-telegram-login="{{ \App\Services\Social\SocialProviders::telegramBotUsername() }}" data-size="medium" data-radius="10" data-auth-url="{{ route('social.telegram') }}"></script></div>
                @endif
            </section>
        @endif

        {{-- Trusted devices --}}
        <section class="card p-6 flex flex-col gap-3">
            <h2 class="m-0 text-lg font-bold">{{ __('Trusted devices') }}</h2>
            @forelse ($devices as $device)
                <div class="flex items-center gap-3 p-3 rounded-[10px] bg-surface-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate">{{ \Illuminate\Support\Str::limit($device->user_agent ?: 'Unknown browser', 48) }}</div>
                        <div class="text-xs text-muted">{{ $device->ip_address }} · last used {{ $device->last_used_at?->diffForHumans() ?? '—' }}
                            @if ($device->id === $currentDeviceId) · <strong class="text-ok">{{ __('this device') }}</strong> @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('account.security.devices.forget', $device) }}">
                        @csrf @method('DELETE')
                        <button class="btn h-9 text-xs" aria-label="{{ __('Remove device') }}">{{ __('Remove') }}</button>
                    </form>
                </div>
            @empty
                <p class="m-0 text-sm text-muted">{{ __('No trusted devices. Tick "Trust this device?" when you log in to add one.') }}</p>
            @endforelse
{{ $devices->links() }}
            @if ($devices->isNotEmpty())
                <form method="POST" action="{{ route('account.security.devices.forget-all') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger w-full">{{ __('Remove all devices') }}</button>
                </form>
            @endif
        </section>
    </div>
</div>
@endsection
