<form method="POST" action="{{ route('admin.settings.social') }}" class="card p-6 flex flex-col gap-4" autocomplete="off">
    @csrf @method('PUT')
    <div class="settings-heading">
        <span class="badge badge-info">Authentication</span>
        <h2>Social sign-in providers</h2>
        <p>Configure OAuth sign-in and account linking. Add each callback URL to the provider app. Secrets are encrypted and never shown again.</p>
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
    <p class="m-0 text-xs text-muted">Save first, then test a provider. The test returns here without signing in or creating an account.</p>
    <div class="settings-actions"><button class="btn btn-primary">Save sign-in providers</button></div>
</form>
