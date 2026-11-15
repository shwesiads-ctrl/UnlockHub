@extends('layouts.admin', ['title' => 'Settings', 'subtitle' => 'Site information, contact details and notifications'])

@section('content')
<div class="settings-page">
<nav class="settings-nav" aria-label="Settings sections">
    <span class="text-xs font-bold uppercase tracking-wide text-muted">Manage settings</span>
    <a href="#site-settings">Site &amp; branding</a>
    <a href="#social-login">Social login</a>
    <a href="#smtp">Email (SMTP)</a>
    <a href="#online-payments">PayPal &amp; Stripe</a>
    <a href="#telegram-alerts">Telegram alerts</a>
    <a href="#monthly-statements">Monthly statements</a>
    <a href="#mobile-app">Mobile app (PWA)</a>
</nav>
<div class="settings-content">
<form id="site-settings" method="POST" action="{{ route('admin.settings.general') }}" enctype="multipart/form-data" class="card p-6 flex flex-col gap-5">
    @csrf @method('PUT')
    <div class="settings-heading"><span class="badge badge-info">General</span><h2 class="m-0 text-xl font-bold">Site &amp; branding</h2><p class="m-0 text-sm text-muted">Manage your store identity, contact information and customer support links.</p></div>
    @php
        $f = fn ($key) => old(str_replace('.', '_', $key), $general[$key] ?? '');
        $fields = [
            ['site.name', 'Site name', 'text', 'UnlockHub'],
            ['site.support_hours', 'Support hours', 'text', '9:00 AM – 10:00 PM'],
            ['contact.email', 'Support email', 'email', 'support@…'],
            ['contact.phone', 'Phone', 'text', '09…'],
            ['contact.address', 'Address', 'text', 'Yangon'],
            ['currency.usd_rate', 'USD exchange rate (Ks)', 'number', '4500'],
        ];
        $social = [
            ['social.telegram', 'Telegram link', 'https://t.me/…'],
            ['social.viber', 'Viber link / number', 'viber://chat?number=…'],
            ['social.facebook', 'Facebook page', 'https://facebook.com/…'],
            ['social.youtube', 'YouTube channel', 'https://youtube.com/@…'],
        ];
    @endphp
    <section class="settings-section">
    <h3 class="m-0 text-base font-bold">Store information</h3>
    <div class="settings-fields">
        @foreach ($fields as [$key, $label, $type, $ph])
            @php $name = str_replace('.', '_', $key); @endphp
            <div>
                <label for="{{ $name }}" class="label">{{ $label }}</label>
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if ($type === 'number') step="0.01" min="0" @endif value="{{ $f($key) }}" placeholder="{{ $ph }}" class="input h-11">
                <x-field-error :name="$name" />
                @if ($key === 'contact.email') <p class="m-0 mt-1 text-xs text-muted">Messages from the contact form are sent here.</p> @endif
                @if ($key === 'currency.usd_rate') <p class="m-0 mt-1 text-xs text-muted">Kyat value of 1 USD, used for USD price display.</p> @endif
            </div>
        @endforeach
        <div>
            <label for="pages_terms_slug" class="label">Terms &amp; Refunds page</label>
            <select id="pages_terms_slug" name="pages_terms_slug" class="input h-11">
                <option value="">— none —</option>
                @foreach ($kbPages as $p) <option value="{{ $p->slug }}" @selected($f('pages.terms_slug') === $p->slug)>{{ $p->title }}</option> @endforeach
            </select>
            <p class="m-0 mt-1 text-xs text-muted">Write it as a Knowledge Base article, then pick it here (shown in the footer).</p>
        </div>
    </div>
    </section>
    <section class="settings-section">
    <h3 class="m-0 text-base font-bold">Logo</h3>
    <div class="settings-fields items-start">
        <div class="settings-upload flex flex-col gap-2">
            <label for="logo" class="label mb-0">Site logo <span class="font-normal text-muted">(PNG/WebP, transparent, ~ 300×80)</span></label>
            @if ($logo = setting('site.logo'))
                <div class="p-3 rounded-xl bg-surface-2 flex items-center gap-3"><img src="{{ Storage::disk('public')->url($logo) }}" alt="Current logo" class="h-10 w-auto max-w-[200px] object-contain"><label class="flex items-center gap-2 text-xs text-muted ml-auto"><input type="checkbox" name="remove_logo" value="1">Remove</label></div>
            @endif
            <input id="logo" name="logo" type="file" accept="image/*" class="text-sm">
            <x-field-error name="logo" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="logo_show_name" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('site.logo_show_name', '1') === '1')>Show site name next to the logo</label>
        </div>
        <div class="settings-upload flex flex-col gap-2">
            <label for="favicon" class="label mb-0">Browser tab icon <span class="font-normal text-muted">(PNG/ICO, 64×64)</span></label>
            @if ($fav = setting('site.favicon'))
                <div class="p-3 rounded-xl bg-surface-2 flex items-center gap-3"><img src="{{ Storage::disk('public')->url($fav) }}" alt="Current icon" class="w-8 h-8 object-contain"><label class="flex items-center gap-2 text-xs text-muted ml-auto"><input type="checkbox" name="remove_favicon" value="1">Remove</label></div>
            @endif
            <input id="favicon" name="favicon" type="file" accept=".png,.ico" class="text-sm">
            <x-field-error name="favicon" />
        </div>
    </div>
    </section>
    <section class="settings-section">
    <h3 class="m-0 text-base font-bold">Social &amp; support channels</h3>
    <p class="m-0 text-sm text-muted">These links appear on your public website.</p>
    <div class="settings-fields">
        @foreach ($social as [$key, $label, $ph])
            @php $name = str_replace('.', '_', $key); @endphp
            <div>
                <label for="{{ $name }}" class="label">{{ $label }}</label>
                <input id="{{ $name }}" name="{{ $name }}" value="{{ $f($key) }}" placeholder="{{ $ph }}" class="input h-11">
                <x-field-error :name="$name" />
            </div>
        @endforeach
    </div>
    </section>
    <div class="settings-actions"><span class="text-xs text-muted">Save to apply your site and branding changes.</span><button class="btn btn-primary">Save site settings</button></div>
</form>

<form id="social-login" method="POST" action="{{ route('admin.settings.social') }}" class="card p-6 flex flex-col gap-4" autocomplete="off">
    @csrf @method('PUT')
    <div class="flex flex-col gap-1">
        <h2 class="m-0 text-lg font-bold">Social login</h2>
        <p class="m-0 text-sm text-muted">Create an app on each platform, paste its ID and secret, and add the <strong class="text-ink">Redirect URL</strong> shown below to that app. Secrets are stored encrypted and never shown again.</p>
    </div>
    <div class="settings-fields">
        @foreach ($socialProviders as $key => $p)
            @php $on = setting("social_login.{$key}.enabled") === '1'; $hasSecret = filled(setting("social_login.{$key}.client_secret")); @endphp
            <fieldset class="m-0 min-w-0 rounded-xl border border-line p-4 flex flex-col gap-3">
                <legend class="px-1 font-bold">{{ $p['label'] }}</legend>
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="{{ $key }}_enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked($on)>Enabled</label>
                @if ($key === 'telegram')
                    <div><label for="tg_user" class="label text-xs">Bot username</label><input id="tg_user" name="telegram_bot_username" value="{{ setting('social_login.telegram.bot_username') }}" class="input h-10" placeholder="@YourShopBot"><x-field-error name="telegram_bot_username" /></div>
                    <div><label for="tg_token" class="label text-xs">Bot token <span class="font-normal text-muted">(empty = use the alerts bot)</span></label><input id="tg_token" name="telegram_bot_token" type="password" class="input h-10 font-mono" autocomplete="new-password" placeholder="{{ filled(setting('social_login.telegram.bot_token')) ? 'Saved — leave empty to keep' : '123456:ABC…' }}"><x-field-error name="telegram_bot_token" /></div>
                    <p class="m-0 text-xs text-muted">In @BotFather send <code>/setdomain</code> and set <code>{{ parse_url(url('/'), PHP_URL_HOST) }}</code>. Telegram only works on a real domain with HTTPS.</p>
                @else
                    <div><label for="{{ $key }}_id" class="label text-xs">{{ $key === 'tiktok' ? 'Client key' : ($key === 'facebook' ? 'App ID' : 'Client ID') }}</label><input id="{{ $key }}_id" name="{{ $key }}_client_id" value="{{ setting("social_login.{$key}.client_id") }}" class="input h-10 font-mono text-sm"></div>
                    <div><label for="{{ $key }}_secret" class="label text-xs">{{ $key === 'facebook' ? 'App secret' : 'Client secret' }}</label><input id="{{ $key }}_secret" name="{{ $key }}_client_secret" type="password" class="input h-10 font-mono text-sm" autocomplete="new-password" placeholder="{{ $hasSecret ? 'Saved — leave empty to keep' : '' }}"></div>
                    <div class="text-xs text-muted">Redirect URL: <code class="text-ink break-all select-all">{{ route('social.callback', $key) }}</code> <button type="button" class="btn h-7 px-2 text-[11px]" data-copy="{{ route('social.callback', $key) }}">Copy</button></div>
                @endif
                <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-line">
                    @if ($v = setting("social_login.{$key}.verified_at"))
                        <span class="badge badge-success">Tested ✓ {{ \Illuminate\Support\Carbon::parse($v)->diffForHumans() }}</span>
                    @else
                        <span class="badge badge-pending">Not tested</span>
                    @endif
                    <a href="{{ route('admin.settings.social.test', $key) }}" class="btn h-8 text-xs">Test {{ $key === 'telegram' ? 'bot' : 'login' }}</a>
                    <a href="{{ $p['console'] }}" target="_blank" rel="noopener" class="text-xs ml-auto">Developer console →</a>
                </div>
            </fieldset>
        @endforeach
    </div>
    <p class="m-0 text-xs text-muted">Save first, then <strong>Test</strong>: you go through the real sign-in and come back here with the result — nobody is signed in or created.</p>
    <button class="btn btn-primary self-start">Save social login</button>
</form>

<section id="smtp" class="card p-6 flex flex-col gap-4">
    <div class="flex justify-between items-center gap-3 flex-wrap">
        <div>
            <h2 class="m-0 text-lg font-bold">Email (SMTP)</h2>
            <p class="m-0 text-sm text-muted">Order results, receipts, statements and password resets are sent with this. cPanel: Email Accounts › Connect Devices shows the values.</p>
        </div>
        @if (setting('mail.verified_at'))
            <span class="badge badge-success">Tested ✓ {{ \Illuminate\Support\Carbon::parse(setting('mail.verified_at'))->diffForHumans() }}</span>
        @elseif (setting('mail.host'))
            <span class="badge badge-pending">Not tested</span>
        @else
            <span class="badge badge-pending">Using .env ({{ config('mail.default') }})</span>
        @endif
    </div>
    <form method="POST" action="{{ route('admin.settings.mail') }}" class="flex flex-col gap-3" autocomplete="off">
        @csrf @method('PUT')
        <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(180px,1fr))]">
            <div class="col-span-2 min-w-0"><label for="m_host" class="label text-xs">SMTP host</label><input id="m_host" name="host" value="{{ setting('mail.host') }}" class="input h-10" placeholder="mail.yourdomain.com / smtp.gmail.com"></div>
            <div><label for="m_port" class="label text-xs">Port</label><input id="m_port" name="port" type="number" value="{{ setting('mail.port', 587) }}" class="input h-10"></div>
            <div><label for="m_enc" class="label text-xs">Security</label>
                <select id="m_enc" name="encryption" class="input h-10">@foreach (['tls' => 'TLS / STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None (25)'] as $k => $l)<option value="{{ $k }}" @selected(setting('mail.encryption', 'tls') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><label for="m_user" class="label text-xs">Username</label><input id="m_user" name="username" value="{{ setting('mail.username') }}" class="input h-10" autocomplete="off"></div>
            <div><label for="m_pass" class="label text-xs">Password</label><input id="m_pass" name="password" type="password" class="input h-10" autocomplete="new-password" placeholder="{{ setting('mail.password') ? 'Saved — leave empty to keep' : '' }}"></div>
            <div><label for="m_from" class="label text-xs">From address</label><input id="m_from" name="from_address" type="email" value="{{ setting('mail.from_address', config('mail.from.address')) }}" class="input h-10" required></div>
            <div><label for="m_fname" class="label text-xs">From name</label><input id="m_fname" name="from_name" value="{{ setting('mail.from_name', setting('site.name')) }}" class="input h-10"></div>
        </div>
        @if (setting('mail.password')) <label class="flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_password" value="1">Remove saved password</label> @endif
        <p class="m-0 text-xs text-muted">Gmail: use an <strong>App password</strong> (Google account › Security › 2-Step Verification › App passwords), host smtp.gmail.com, port 587 TLS.</p>
        <button class="btn btn-primary self-start">Save email settings</button>
    </form>
    <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="flex flex-wrap gap-2 items-end pt-3 border-t border-line">
        @csrf
        <div class="flex-1 min-w-56"><label for="m_to" class="label text-xs">Send a test email to</label><input id="m_to" name="to" type="email" value="{{ auth()->user()->email }}" class="input h-10" required></div>
        <button class="btn h-10">Send test email</button>
    </form>
</section>

@include('admin.settings._payments')

<div class="flex flex-wrap gap-5 items-start">
    <form id="telegram-alerts" method="POST" action="{{ route('admin.settings.telegram') }}" class="flex-[999_1_520px] min-w-0 card p-6 flex flex-col gap-4" autocomplete="off">
        @csrf @method('PUT')
        <div class="flex justify-between items-center gap-3">
            <h2 class="m-0 text-lg font-bold">Telegram admin alerts</h2>
            @if ($telegramConfigured && $chatId)
                <span class="badge badge-success">Connected</span>
            @else
                <span class="badge badge-pending">Not set up</span>
            @endif
        </div>

        <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(220px,1fr))]">
            <div>
                <label for="bot_token" class="label">Bot token</label>
                <input id="bot_token" name="bot_token" type="password" class="input font-mono" autocomplete="new-password"
                       placeholder="{{ $telegramConfigured ? 'Saved — leave empty to keep' : '123456789:ABCdef…' }}">
                <x-field-error name="bot_token" />
                @if ($telegramConfigured)
                    <label class="flex items-center gap-2 text-xs text-muted mt-1.5"><input type="checkbox" name="remove_token" value="1">Remove saved token</label>
                @endif
            </div>
            <div>
                <label for="chat_id" class="label">Chat ID</label>
                <input id="chat_id" name="chat_id" value="{{ old('chat_id', $chatId) }}" class="input font-mono" placeholder="123456789 or -100…">
                <x-field-error name="chat_id" />
            </div>
        </div>

        <fieldset class="m-0 p-0 border-0 flex flex-col gap-2">
            <legend class="label">Send me a message when…</legend>
            @foreach ($events as $key => $label)
                <label class="flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="events[]" value="{{ $key }}" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('telegram.event.'.$key, '1') === '1')>
                    {{ $label }}
                </label>
            @endforeach
        </fieldset>

        <div class="max-w-xs">
            <label for="low_balance" class="label">"Low balance" below (supplier currency)</label>
            <input id="low_balance" name="low_balance" type="number" min="0" step="0.01" value="{{ old('low_balance', $lowBalance) }}" class="input h-11">
        </div>

        <div class="flex gap-2 flex-wrap">
            <button class="btn btn-primary">Save</button>
            <button form="tg-test" class="btn" @disabled(! ($telegramConfigured && $chatId))>Send test message</button>
        </div>
    </form>
    <form id="tg-test" method="POST" action="{{ route('admin.settings.telegram.test') }}">@csrf</form>

    <aside class="flex-[1_1_320px] min-w-0 flex flex-col gap-5">
        <section class="card p-5 flex flex-col gap-2.5 text-sm leading-relaxed">
            <h2 class="m-0 text-lg font-bold">How to set up</h2>
            <ol class="m-0 pl-5 flex flex-col gap-1.5 text-muted">
                <li>In Telegram, open <strong class="text-ink">@BotFather</strong> → <code>/newbot</code> → copy the token.</li>
                <li>Open your new bot and press <strong class="text-ink">Start</strong> (or add it to your admin group).</li>
                <li>Get your chat ID: message <strong class="text-ink">@userinfobot</strong> (for a group, the ID starts with <code>-100</code>).</li>
                <li>Paste both here, Save, then <strong class="text-ink">Send test message</strong>.</li>
            </ol>
        </section>
        <form id="mobile-app" method="POST" action="{{ route('admin.settings.pwa') }}" enctype="multipart/form-data" class="card p-5 flex flex-col gap-3 text-sm">
            @csrf @method('PUT')
            <h2 class="m-0 text-lg font-bold">Mobile app (PWA)</h2>
            <p class="m-0 text-muted">Customers can add the site to their home screen (Android: browser menu › Install app; iPhone: Share › Add to Home Screen).</p>
            <div class="flex items-center gap-3">
                <img src="{{ setting('pwa.icon_version') ? Storage::disk('public')->url('pwa/icon-192.png').'?v='.setting('pwa.icon_version') : asset('icons/icon-192.png') }}" alt="App icon" class="w-14 h-14 rounded-xl">
                <div class="flex-1"><label for="pwa_icon" class="label text-xs">App icon (PNG/JPG, square, 512×512 best)</label><input id="pwa_icon" name="icon" type="file" accept="image/png,image/jpeg" class="input py-2"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label for="pwa_short" class="label text-xs">Name under the icon (max 20)</label><input id="pwa_short" name="short_name" maxlength="20" value="{{ setting('pwa.short_name') }}" class="input h-10" placeholder="{{ \Illuminate\Support\Str::limit(setting('site.name', config('app.name')), 12, '') }}"></div>
                <div><label for="pwa_color" class="label text-xs">Theme colour</label><input id="pwa_color" name="theme_color" type="color" value="{{ setting('pwa.theme_color', '#2459E8') }}" class="input h-10 p-1"></div>
            </div>
            <div class="rounded-[10px] bg-surface-2 p-3 flex flex-col gap-2">
                <span class="font-semibold">Push notifications</span>
                @if (\App\Services\WebPush::publicKey())
                    <label class="flex items-center gap-2"><input type="checkbox" name="push_enabled" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('push.enabled') === '1')>On — {{ \App\Models\PushSubscription::count() }} device(s) subscribed</label>
                @else
                    <span class="text-muted">Not set up yet. Create keys below (needs HTTPS on the live site).</span>
                @endif
                <span class="text-xs text-muted">Pushes carry no personal data: the phone asks the site for the latest notification.</span>
            </div>
            <button class="btn btn-primary self-start">Save app settings</button>
        </form>
        <form method="POST" action="{{ route('admin.settings.push-keys') }}" class="card p-5 flex flex-wrap gap-3 items-center text-sm" onsubmit="return confirm('{{ \App\Services\WebPush::publicKey() ? 'New keys sign everyone out of push; they must enable it again. Continue?' : 'Create push keys?' }}')">
            @csrf
            <span class="flex-1">{{ \App\Services\WebPush::publicKey() ? 'Push keys exist.' : 'Create the VAPID keys used to send push notifications.' }}</span>
            <button class="btn">{{ \App\Services\WebPush::publicKey() ? 'Replace push keys' : 'Create push keys' }}</button>
        </form>
        <form id="monthly-statements" method="POST" action="{{ route('admin.settings.statements') }}" class="card p-5 flex flex-col gap-3 text-sm">
            @csrf @method('PUT')
            <h2 class="m-0 text-lg font-bold">Monthly statements</h2>
            <label class="flex items-start gap-2.5">
                <input type="checkbox" name="monthly" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('statements.monthly', '1') === '1')>
                <span>On the 1st of each month, email last month's PDF statement to every customer who had wallet activity (and has email alerts on).</span>
            </label>
            <button class="btn self-start">Save</button>
        </form>
        <section class="card p-5 flex flex-col gap-2 text-sm leading-relaxed">
            <h2 class="m-0 text-lg font-bold">Customer emails</h2>
            <p class="m-0 text-muted">Users get emails for finished orders, bulk-order summaries and top-up decisions (they can turn emails off; site notifications always show).</p>
            <p class="m-0">Mail driver: <code>{{ $mailDriver }}</code>
                @if ($mailDriver === 'log') <span class="text-warn">— emails are only written to <code>storage/logs/laravel.log</code>. Set SMTP in <code>.env</code> to really send.</span> @endif
            </p>
        </section>
    </aside>
</div>
</div>
</div>
@endsection
