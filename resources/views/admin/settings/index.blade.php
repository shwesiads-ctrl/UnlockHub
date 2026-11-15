@extends('layouts.admin', ['title' => 'Settings', 'subtitle' => 'Site information, contact details and notifications'])

@section('content')
<form method="POST" action="{{ route('admin.settings.general') }}" class="card p-6 flex flex-col gap-5">
    @csrf @method('PUT')
    <h2 class="m-0 text-lg font-bold">Site</h2>
    @php
        $f = fn ($key) => old(str_replace('.', '_', $key), $general[$key] ?? '');
        $fields = [
            ['site.name', 'Site name', 'text', 'UnlockHub'],
            ['site.support_hours', 'Support hours', 'text', '9:00 AM – 10:00 PM'],
            ['contact.email', 'Contact email (receives contact form)', 'email', 'support@…'],
            ['contact.phone', 'Phone', 'text', '09…'],
            ['contact.address', 'Address', 'text', 'Yangon'],
            ['currency.usd_rate', '1 USD = ? Ks (for USD price display)', 'number', '4500'],
        ];
        $social = [
            ['social.telegram', 'Telegram link', 'https://t.me/…'],
            ['social.viber', 'Viber link / number', 'viber://chat?number=…'],
            ['social.facebook', 'Facebook page', 'https://facebook.com/…'],
            ['social.youtube', 'YouTube channel', 'https://youtube.com/@…'],
        ];
    @endphp
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
        @foreach ($fields as [$key, $label, $type, $ph])
            @php $name = str_replace('.', '_', $key); @endphp
            <div>
                <label for="{{ $name }}" class="label">{{ $label }}</label>
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if ($type === 'number') step="0.01" min="0" @endif value="{{ $f($key) }}" placeholder="{{ $ph }}" class="input h-11">
                <x-field-error :name="$name" />
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
    <h3 class="m-0 text-base font-bold">Social &amp; support channels</h3>
    <div class="grid gap-4 grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
        @foreach ($social as [$key, $label, $ph])
            @php $name = str_replace('.', '_', $key); @endphp
            <div>
                <label for="{{ $name }}" class="label">{{ $label }}</label>
                <input id="{{ $name }}" name="{{ $name }}" value="{{ $f($key) }}" placeholder="{{ $ph }}" class="input h-11">
                <x-field-error :name="$name" />
            </div>
        @endforeach
    </div>
    <button class="btn btn-primary self-start">Save site settings</button>
</form>

<div class="flex flex-wrap gap-5 items-start">
    <form method="POST" action="{{ route('admin.settings.telegram') }}" class="flex-[999_1_520px] min-w-0 card p-6 flex flex-col gap-4" autocomplete="off">
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
        <section class="card p-5 flex flex-col gap-2 text-sm leading-relaxed">
            <h2 class="m-0 text-lg font-bold">Customer emails</h2>
            <p class="m-0 text-muted">Users get emails for finished orders, bulk-order summaries and top-up decisions (they can turn emails off; site notifications always show).</p>
            <p class="m-0">Mail driver: <code>{{ $mailDriver }}</code>
                @if ($mailDriver === 'log') <span class="text-warn">— emails are only written to <code>storage/logs/laravel.log</code>. Set SMTP in <code>.env</code> to really send.</span> @endif
            </p>
        </section>
    </aside>
</div>
@endsection
