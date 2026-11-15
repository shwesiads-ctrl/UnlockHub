@extends('layouts.account', ['title' => 'Settings & Security'])

@section('content')
<div class="flex flex-wrap gap-5 items-start">
    <div class="flex-[999_1_520px] min-w-0 flex flex-col gap-5">

        {{-- Profile --}}
        <section class="card p-6 flex flex-col gap-4">
            <h2 class="m-0 text-lg font-bold">Profile</h2>
            <form method="POST" action="{{ route('account.security.profile') }}" class="flex flex-col gap-4">
                @csrf @method('PUT')
                <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
                    <div>
                        <label for="name" class="label">Full name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" class="input" required>
                        <x-field-error name="name" />
                    </div>
                    <div>
                        <label for="phone" class="label">Phone</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" class="input">
                        <x-field-error name="phone" />
                    </div>
                    <div>
                        <label class="label">Username</label>
                        <input type="text" value="{{ $user->username }}" class="input opacity-70" disabled>
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input type="email" value="{{ $user->email }}" class="input opacity-70" disabled>
                    </div>
                    <div>
                        <label for="preferred_locale" class="label">Language</label>
                        <select id="preferred_locale" name="preferred_locale" class="input">
                            @foreach (config('unlockhub.locales') as $code => $label)
                                <option value="{{ $code }}" @selected($user->preferred_locale === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="preferred_currency" class="label">Show prices in</label>
                        <select id="preferred_currency" name="preferred_currency" class="input">
                            @foreach (config('unlockhub.currencies') as $c)
                                <option value="{{ $c }}" @selected($user->preferred_currency === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary self-start">Save profile</button>
            </form>
        </section>

        {{-- Password --}}
        <section class="card p-6 flex flex-col gap-4">
            <h2 class="m-0 text-lg font-bold">Change password</h2>
            <form method="POST" action="{{ route('account.security.password') }}" class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
                @csrf @method('PUT')
                <div>
                    <label for="current_password" class="label">Current password</label>
                    <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password" required>
                    <x-field-error name="current_password" />
                </div>
                <div>
                    <label for="password" class="label">New password</label>
                    <input id="password" name="password" type="password" class="input" autocomplete="new-password" required>
                    <x-field-error name="password" />
                </div>
                <div>
                    <label for="password_confirmation" class="label">Confirm</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
                </div>
                <div class="col-span-full"><button class="btn btn-primary">Change password</button></div>
            </form>
        </section>
    </div>

    <div class="flex-[1_1_340px] min-w-0 flex flex-col gap-5">

        {{-- Google Authenticator --}}
        <section class="card p-6 flex flex-col gap-4">
            <div class="flex justify-between items-center gap-3">
                <h2 class="m-0 text-lg font-bold">Google Authenticator</h2>
                @if ($user->hasTwoFactor())
                    <span class="badge badge-success">On</span>
                @else
                    <span class="badge badge-pending">Off</span>
                @endif
            </div>

            @if ($user->hasTwoFactor())
                <p class="m-0 text-sm text-muted leading-relaxed">You'll be asked for a 6-digit code from the app when you log in on a new device.</p>
                <form method="POST" action="{{ route('account.security.2fa.disable') }}" class="flex flex-col gap-3">
                    @csrf @method('DELETE')
                    <div>
                        <label for="disable_password" class="label">Password to turn off</label>
                        <input id="disable_password" name="password" type="password" class="input h-11" required>
                        @error('password', 'twoFactor') <p class="mt-1.5 mb-0 text-[13px] font-medium text-danger">{{ $message }}</p> @enderror
                    </div>
                    <button class="btn btn-danger">Turn off</button>
                </form>
            @elseif ($qr)
                <p class="m-0 text-sm text-muted leading-relaxed">1. Scan this QR code with Google Authenticator.<br>2. Enter the 6-digit code it shows.</p>
                <div class="self-center p-3 rounded-xl bg-white" role="img" aria-label="QR code for Google Authenticator">{!! $qr !!}</div>
                <p class="m-0 text-xs text-muted break-all">Can't scan? Enter this key: <span class="font-mono text-ink">{{ $user->two_factor_secret }}</span></p>
                <form method="POST" action="{{ route('account.security.2fa.confirm') }}" class="flex flex-col gap-3">
                    @csrf
                    <div>
                        <label for="code" class="label">6-digit code</label>
                        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="input text-center font-mono text-xl tracking-[0.3em]" required>
                        <x-field-error name="code" />
                    </div>
                    <button class="btn btn-primary">Confirm &amp; turn on</button>
                </form>
            @else
                <p class="m-0 text-sm text-muted leading-relaxed">Without it, we email you a code when you log in on a new device. The app is faster and works offline.</p>
                <form method="POST" action="{{ route('account.security.2fa.start') }}">
                    @csrf
                    <button class="btn btn-primary w-full">Set up Google Authenticator</button>
                </form>
            @endif
        </section>

        {{-- Trusted devices --}}
        <section class="card p-6 flex flex-col gap-3">
            <h2 class="m-0 text-lg font-bold">Trusted devices</h2>
            @forelse ($devices as $device)
                <div class="flex items-center gap-3 p-3 rounded-[10px] bg-surface-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate">{{ \Illuminate\Support\Str::limit($device->user_agent ?: 'Unknown browser', 48) }}</div>
                        <div class="text-xs text-muted">{{ $device->ip_address }} · last used {{ $device->last_used_at?->diffForHumans() ?? '—' }}
                            @if ($device->id === $currentDeviceId) · <strong class="text-ok">this device</strong> @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('account.security.devices.forget', $device) }}">
                        @csrf @method('DELETE')
                        <button class="btn h-9 text-xs" aria-label="Remove device">Remove</button>
                    </form>
                </div>
            @empty
                <p class="m-0 text-sm text-muted">No trusted devices. Tick "Trust this device?" when you log in to add one.</p>
            @endforelse
            @if ($devices->isNotEmpty())
                <form method="POST" action="{{ route('account.security.devices.forget-all') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger w-full">Remove all devices</button>
                </form>
            @endif
        </section>
    </div>
</div>
@endsection
