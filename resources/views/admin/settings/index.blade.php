@extends('layouts.admin', ['title' => 'Settings', 'subtitle' => 'Site information, contact details and notifications'])

@section('content')
<div class="settings-page">
<nav class="settings-nav" aria-label="Settings sections" role="tablist" aria-orientation="vertical">
    <span class="text-xs font-bold uppercase tracking-wide text-muted">Manage settings</span>
    <a id="tab-site-settings" href="#site-settings" role="tab" aria-controls="site-settings">Site &amp; branding</a>
    <a id="tab-home-page" href="#home-page" role="tab" aria-controls="home-page">Home page</a>
    <a id="tab-social-login" href="#social-login" role="tab" aria-controls="social-login">Social login</a>
    <a id="tab-security" href="#security" role="tab" aria-controls="security">Security (CAPTCHA / Cloudflare)</a>
    <a id="tab-smtp" href="#smtp" role="tab" aria-controls="smtp">Email (SMTP)</a>
    <a id="tab-online-payments" href="#online-payments" role="tab" aria-controls="online-payments">PayPal &amp; Stripe</a>
    <a id="tab-telegram-alerts" href="#telegram-alerts" role="tab" aria-controls="telegram-alerts">Telegram alerts</a>
    <a id="tab-monthly-statements" href="#monthly-statements" role="tab" aria-controls="monthly-statements">Monthly statements</a>
    <a id="tab-mobile-app" href="#mobile-app" role="tab" aria-controls="mobile-app">Mobile app (PWA)</a>
</nav>
<div class="settings-content" data-settings-content>
<div id="site-settings" class="settings-panel" role="tabpanel" tabindex="0" aria-labelledby="tab-site-settings">
<form method="POST" action="{{ route('admin.settings.general') }}" enctype="multipart/form-data" class="card p-6 flex flex-col gap-5">
    @csrf @method('PUT')
    <div class="settings-heading">
        <span class="badge badge-info">General</span>
        <h2>Site &amp; branding</h2>
        <p>Manage your store identity, contact information and customer support links.</p>
    </div>
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
    <h3>Store information</h3>
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
    <h3>Logo</h3>
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
    <h3>Social &amp; support channels</h3>
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
    <div class="settings-actions">
        <span>Save to apply your site and branding changes.</span>
        <button class="btn btn-primary">Save site settings</button>
    </div>
</form>
<form method="POST" action="{{ route('admin.settings.account-display') }}" class="card p-5 flex flex-col gap-4">
    @csrf @method('PUT')
    <div><h2 class="m-0 text-lg font-bold">Account header badges</h2><p class="m-0 mt-1 text-sm text-muted">Choose which balance badges appear in the account header for signed-in users.</p></div>
    <div class="flex flex-wrap gap-x-8 gap-y-3">
        <label class="flex items-center gap-2.5 text-sm font-semibold"><input type="checkbox" name="show_balance" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('account.show_balance', '1') === '1')>Show wallet balance (Ks)</label>
        <label class="flex items-center gap-2.5 text-sm font-semibold"><input type="checkbox" name="show_points" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('account.show_points', '1') === '1')>Show points</label>
    </div>
    <button class="btn btn-primary self-start">Save header display</button>
</form>
</div>

<div id="home-page" class="settings-panel" role="tabpanel" tabindex="0" aria-labelledby="tab-home-page">
<form method="POST" action="{{ route('admin.settings.home-page') }}" class="card p-6 flex flex-col gap-5">
    @csrf @method('PUT')
    @php
        $hf = fn ($key, $default = '') => old($key, setting('home.'.$key, $default));
        $homeTextFields = [
            ['hero_badge', 'Small hero badge', 'For repair shops & resellers in Myanmar', 1],
            ['hero_title', 'Main headline', 'Unlock codes, tool credits & activations — in one wallet.', 2],
            ['hero_description', 'Hero description', 'Top up with :methods, place IMEI and server orders, track every result, and earn points on each successful order.', 3],
            ['browse_label', 'Browse button text', 'Browse services', 1],
            ['trust_1', 'Trust point 1', 'Auto refund on rejected orders', 1],
            ['trust_2', 'Trust point 2', 'Reseller group pricing', 1],
            ['trust_3', 'Trust point 3', '2FA secured accounts', 1],
        ];
        $categoryFields = [
            ['category_imei_title', 'IMEI / serial card title', 'IMEI / Serial Services'],
            ['category_imei_description', 'IMEI / serial card description', 'Carrier unlocks, NCK/MCK codes, IMEI & GSX checks. Submit IMEI, SN or ECID.'],
            ['category_server_title', 'Server / tool card title', 'Server · Box · Tool Credits'],
            ['category_server_description', 'Server / tool card description', 'UnlockTool, Chimera, TFM, dongle renewals, game credits and gift cards.'],
            ['category_remote_title', 'Remote / files card title', 'Remote · Rent · Files'],
            ['category_remote_description', 'Remote / files card description', 'Live remote sessions, hourly tool rental and paid firmware files.'],
        ];
        $stepFields = [
            ['step_1_title', 'Step 1 title', 'Register & verify'], ['step_1_description', 'Step 1 description', 'Email OTP and optional Google Authenticator.'],
            ['step_2_title', 'Step 2 title', 'Add funds'], ['step_2_description', 'Step 2 description', 'Transfer by mobile pay, upload the receipt, admin approves.'],
            ['step_3_title', 'Step 3 title', 'Place orders'], ['step_3_description', 'Step 3 description', 'Single or bulk IMEI. Credit is held until the result arrives.'],
            ['step_4_title', 'Step 4 title', 'Get result & points'], ['step_4_description', 'Step 4 description', 'Code delivered to your orders page; points added on success.'],
        ];
    @endphp
    <div class="settings-heading">
        <span class="badge badge-info">Homepage</span>
        <h2>Home page content</h2>
        <p>Edit the public homepage copy and choose which sections visitors see.</p>
    </div>
    <section class="settings-section">
        <h3>Hero</h3>
        <div class="settings-fields">
            @foreach ($homeTextFields as [$key, $label, $default, $rows])
                <div>
                    <label for="{{ $key }}" class="label">{{ $label }}</label>
                    @if ($rows > 1)
                        <textarea id="{{ $key }}" name="{{ $key }}" rows="{{ $rows }}" class="input">{{ $hf($key, $default) }}</textarea>
                    @else
                        <input id="{{ $key }}" name="{{ $key }}" value="{{ $hf($key, $default) }}" class="input h-11">
                    @endif
                    <x-field-error :name="$key" />
                </div>
            @endforeach
        </div>
        <div class="flex flex-col gap-3 mt-4">
            <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="show_reseller_cta" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('show_reseller_cta', setting('home.reseller_cta_enabled', '1')) === '1')>Show Become Reseller button</label>
            <div class="max-w-xl"><label for="reseller_cta_label" class="label">Become Reseller button text</label><input id="reseller_cta_label" name="reseller_cta_label" value="{{ $hf('reseller_cta_label', 'Become a reseller') }}" class="input h-11"><x-field-error name="reseller_cta_label" /><p class="m-0 mt-1 text-xs text-muted">Visible to visitors and regular users; hidden from reseller accounts and administrators.</p></div>
        </div>
    </section>
    <section class="settings-section">
        <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="m-0">Service categories</h3><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_categories" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('show_categories', setting('home.show_categories', '1')) === '1')>Show section</label></div>
        <div class="settings-fields mt-4">
            <div><label for="categories_title" class="label">Section heading</label><input id="categories_title" name="categories_title" value="{{ $hf('categories_title', 'Service categories') }}" class="input h-11"><x-field-error name="categories_title" /></div>
            @foreach ($categoryFields as [$key, $label, $default])
                <div>
                    <label for="{{ $key }}" class="label">{{ $label }}</label>
                    @if (str_ends_with($key, '_description')) <textarea id="{{ $key }}" name="{{ $key }}" rows="2" class="input">{{ $hf($key, $default) }}</textarea>
                    @else <input id="{{ $key }}" name="{{ $key }}" value="{{ $hf($key, $default) }}" class="input h-11"> @endif
                    <x-field-error :name="$key" />
                </div>
            @endforeach
        </div>
    </section>
    <section class="settings-section">
        <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="m-0">Hot services</h3><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_hot_services" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('show_hot_services', setting('home.show_hot_services', '1')) === '1')>Show section</label></div>
        <div class="max-w-xl mt-4"><label for="hot_title" class="label">Section heading</label><input id="hot_title" name="hot_title" value="{{ $hf('hot_title', 'Hot services') }}" class="input h-11"><x-field-error name="hot_title" /></div>
    </section>
    <section class="settings-section">
        <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="m-0">How it works</h3><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_how_it_works" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('show_how_it_works', setting('home.show_how_it_works', '1')) === '1')>Show section</label></div>
        <div class="settings-fields mt-4">
            <div><label for="how_title" class="label">Section heading</label><input id="how_title" name="how_title" value="{{ $hf('how_title', 'How it works') }}" class="input h-11"><x-field-error name="how_title" /></div>
            @foreach ($stepFields as [$key, $label, $default])
                <div>
                    <label for="{{ $key }}" class="label">{{ $label }}</label>
                    @if (str_ends_with($key, '_description')) <textarea id="{{ $key }}" name="{{ $key }}" rows="2" class="input">{{ $hf($key, $default) }}</textarea>
                    @else <input id="{{ $key }}" name="{{ $key }}" value="{{ $hf($key, $default) }}" class="input h-11"> @endif
                    <x-field-error :name="$key" />
                </div>
            @endforeach
        </div>
    </section>
    <section class="settings-section">
        <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="m-0">Payment banner</h3><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_payment_banner" value="1" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(old('show_payment_banner', setting('home.show_payment_banner', '1')) === '1')>Show section</label></div>
        <div class="settings-fields mt-4">
            <div><label for="payment_title" class="label">Custom heading <span class="font-normal text-muted">(leave blank for country-based heading)</span></label><input id="payment_title" name="payment_title" value="{{ $hf('payment_title') }}" class="input h-11"><x-field-error name="payment_title" /></div>
            <div><label for="payment_description" class="label">Description</label><textarea id="payment_description" name="payment_description" rows="2" class="input">{{ $hf('payment_description', 'Transfer, upload your receipt and your wallet is credited after approval.') }}</textarea><x-field-error name="payment_description" /></div>
        </div>
    </section>
    <div class="settings-actions"><span>Changes apply to the public homepage.</span><button class="btn btn-primary">Save home page</button></div>
</form>
</div>

<div id="social-login" class="settings-panel" role="tabpanel" tabindex="0" aria-labelledby="tab-social-login">
<form method="POST" action="{{ route('admin.settings.social') }}" class="card p-6 flex flex-col gap-4" autocomplete="off">
    @csrf @method('PUT')
    <div class="settings-heading">
        <span class="badge badge-info">OAuth</span>
        <h2>Social login</h2>
        <p>Create an app on each platform, paste its ID and secret, and add the <strong class="text-ink">Redirect URL</strong> shown below to that app. Secrets are stored encrypted and never shown again.</p>
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
    <div class="settings-actions">
        <button class="btn btn-primary">Save social login</button>
    </div>
</form>
</div>

@include('admin.settings._security')

<section id="smtp" class="settings-panel card p-6 flex flex-col gap-5" role="tabpanel" tabindex="0" aria-labelledby="tab-smtp">
    <div class="settings-heading">
        <span class="badge badge-info">Email</span>
        <h2>Email (SMTP)</h2>
        @if (setting('mail.verified_at'))
            <span class="badge badge-success ml-auto">Tested ✓ {{ \Illuminate\Support\Carbon::parse(setting('mail.verified_at'))->diffForHumans() }}</span>
        @elseif (setting('mail.host'))
            <span class="badge badge-pending ml-auto">Not tested</span>
        @else
            <span class="badge badge-pending ml-auto">Using .env ({{ config('mail.default') }})</span>
        @endif
        <p>Order results, receipts, statements and password resets are sent with this. cPanel: Email Accounts › Connect Devices shows the values.</p>
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
        <div class="settings-actions"><button class="btn btn-primary">Save email settings</button></div>
    </form>
    <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="flex flex-wrap gap-2 items-end pt-3 border-t border-line">
        @csrf
        <div class="flex-1 min-w-56"><label for="m_to" class="label text-xs">Send a test email to</label><input id="m_to" name="to" type="email" value="{{ auth()->user()->email }}" class="input h-10" required></div>
        <button class="btn h-10">Send test email</button>
    </form>
</section>

@include('admin.settings._payments')

<div id="telegram-alerts" class="settings-panel flex flex-col gap-5" role="tabpanel" tabindex="0" aria-labelledby="tab-telegram-alerts">
    <div class="settings-telegram-grid">
    <form method="POST" action="{{ route('admin.settings.telegram') }}" class="min-w-0 card p-6 flex flex-col gap-4" autocomplete="off">
        @csrf @method('PUT')
        <div class="settings-heading">
            <span class="badge badge-info">Alerts</span>
            <h2>Telegram admin alerts</h2>
            @if ($telegramConfigured && $chatId)
                <span class="badge badge-success ml-auto">Connected</span>
            @else
                <span class="badge badge-pending ml-auto">Not set up</span>
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

        <div class="settings-actions">
            <button form="tg-test" class="btn" @disabled(! ($telegramConfigured && $chatId))>Send test message</button>
            <button class="btn btn-primary">Save alerts</button>
        </div>
    </form>
    <form id="tg-test" method="POST" action="{{ route('admin.settings.telegram.test') }}">@csrf</form>

    <aside class="settings-telegram-help min-w-0 flex flex-col gap-5">
        <section class="card p-5 flex flex-col gap-2.5 text-sm leading-relaxed">
            <h2 class="m-0 text-lg font-bold">How to set up</h2>
            <ol class="m-0 pl-5 flex flex-col gap-1.5 text-muted">
                <li>In Telegram, open <strong class="text-ink">@BotFather</strong> → <code>/newbot</code> → copy the token.</li>
                <li>Open your new bot and press <strong class="text-ink">Start</strong> (or add it to your admin group).</li>
                <li>Get your chat ID: message <strong class="text-ink">@userinfobot</strong> (for a group, the ID starts with <code>-100</code>).</li>
                <li>Paste both here, Save, then <strong class="text-ink">Send test message</strong>.</li>
            </ol>
        </section>
    </aside>
    </div>
</div>

<div id="mobile-app" class="settings-panel flex flex-col gap-5" role="tabpanel" tabindex="0" aria-labelledby="tab-mobile-app">
        <form method="POST" action="{{ route('admin.settings.pwa') }}" enctype="multipart/form-data" class="card p-5 flex flex-col gap-3 text-sm">
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
</div>
<div id="monthly-statements" class="settings-panel flex flex-col gap-5" role="tabpanel" tabindex="0" aria-labelledby="tab-monthly-statements">
        <form method="POST" action="{{ route('admin.settings.statements') }}" class="card p-5 flex flex-col gap-3 text-sm">
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
</div>
<div class="settings-page-nav" aria-label="Settings page navigation">
    <a class="btn" data-settings-previous href="#site-settings" aria-label="Previous settings section">‹ Previous</a>
    <span class="settings-page-count" aria-live="polite">Section 1 of 9</span>
    <a class="btn btn-primary" data-settings-next href="#home-page" aria-label="Next settings section">Next ›</a>
</div>
</div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';
    var nav = document.querySelector('.settings-nav');
    if (!nav) return;
    var links = Array.from(nav.querySelectorAll('[role="tab"][href^="#"]'));
    var panels = links.map(function (link) { return document.getElementById(link.getAttribute('href').slice(1)); }).filter(Boolean);

    function activate(id, updateHistory) {
        var selected = panels.some(function (panel) { return panel.id === id; }) ? id : panels[0].id;
        panels.forEach(function (panel) {
            var active = panel.id === selected;
            panel.hidden = !active;
            panel.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        links.forEach(function (link, index) {
            var active = link.getAttribute('href') === '#' + selected;
            link.classList.toggle('active', active);
            link.setAttribute('aria-selected', active ? 'true' : 'false');
            link.tabIndex = active ? 0 : -1;
        });
        var counter = document.querySelector('.settings-page-count');
        var page = panels.findIndex(function (panel) { return panel.id === selected; });
        if (counter) counter.textContent = 'Section ' + (page + 1) + ' of ' + panels.length;
        var previous = document.querySelector('[data-settings-previous]');
        var next = document.querySelector('[data-settings-next]');
        if (previous) previous.href = '#' + panels[(page - 1 + panels.length) % panels.length].id;
        if (next) next.href = '#' + panels[(page + 1) % panels.length].id;
        if (updateHistory && window.location.hash !== '#' + selected) window.location.hash = selected;
    }

    links.forEach(function (link, index) {
        link.addEventListener('keydown', function (event) {
            var target = null;
            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') target = links[(index + 1) % links.length];
            if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') target = links[(index - 1 + links.length) % links.length];
            if (event.key === 'Home') target = links[0];
            if (event.key === 'End') target = links[links.length - 1];
            if (target) { event.preventDefault(); target.focus(); target.click(); }
        });
    });
    window.addEventListener('hashchange', function () { activate(window.location.hash.slice(1), false); });
    document.querySelectorAll('[data-settings-previous], [data-settings-next]').forEach(function (link) {
        link.addEventListener('click', function (event) { event.preventDefault(); activate(link.hash.slice(1), true); });
    });
    var remembered = '';
    try { remembered = window.sessionStorage.getItem('admin-settings-section') || ''; } catch (e) {}
    var initial = window.location.hash.slice(1) || remembered;
    activate(initial, !window.location.hash && !!remembered);
    try { window.sessionStorage.removeItem('admin-settings-section'); } catch (e) {}
    links.forEach(function (link) {
        link.addEventListener('click', function () {
            try { window.sessionStorage.setItem('admin-settings-section', link.hash.slice(1)); } catch (e) {}
        });
    });
    document.querySelectorAll('.settings-content form').forEach(function (form) {
        form.addEventListener('submit', function () {
            try { window.sessionStorage.setItem('admin-settings-section', window.location.hash.slice(1) || panels[0].id); } catch (e) {}
        });
    });
})();
</script>
@endsection
