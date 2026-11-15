{{-- CAPTCHA (Google reCAPTCHA / Cloudflare Turnstile) and Cloudflare proxy --}}
@php
    $cp = \App\Services\Captcha::provider();
    $via = request()->attributes->get('via_cloudflare') === true;
@endphp
<section id="security" class="card p-6 flex flex-col gap-5">
    <div class="settings-heading">
        <span class="badge badge-info">Security</span>
        <h2>Bot check &amp; Cloudflare</h2>
        @if (\App\Services\Captcha::active())
            <span class="badge badge-success ml-auto">Active ✓ {{ \App\Services\Captcha::PROVIDERS[$cp] }}</span>
        @elseif ($cp !== 'none')
            <span class="badge badge-pending ml-auto">Saved — not active until tested</span>
        @else
            <span class="badge bg-surface-2 text-muted ml-auto">Off</span>
        @endif
        <p>Stop bots on login, register and other forms with Google reCAPTCHA or Cloudflare Turnstile.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.security') }}" class="flex flex-col gap-4" autocomplete="off">
        @csrf @method('PUT')
        <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
            <div><label for="cp_p" class="label text-xs">Bot check</label>
                <select id="cp_p" name="provider" class="input h-10">@foreach (\App\Services\Captcha::PROVIDERS as $k => $l)<option value="{{ $k }}" @selected($cp === $k)>{{ $l }}</option>@endforeach</select></div>
            <div><label for="cp_site" class="label text-xs">Site key</label><input id="cp_site" name="site_key" value="{{ \App\Services\Captcha::siteKey() }}" class="input h-10 font-mono text-sm"></div>
            <div><label for="cp_sec" class="label text-xs">Secret key</label><input id="cp_sec" name="secret" type="password" class="input h-10 font-mono text-sm" autocomplete="new-password" placeholder="{{ setting('captcha.secret') ? 'Saved — leave empty to keep' : '' }}"></div>
            <div><label for="cp_score" class="label text-xs">reCAPTCHA v3 minimum score</label><input id="cp_score" name="min_score" type="number" step="0.1" min="0.1" max="0.9" value="{{ setting('captcha.min_score', 0.5) }}" class="input h-10"></div>
        </div>
        <fieldset class="m-0 p-0 border-0 flex flex-wrap gap-x-5 gap-y-2">
            <legend class="label">Protect these forms</legend>
            @foreach (\App\Services\Captcha::FORMS as $k => $l)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="forms[]" value="{{ $k }}" class="w-5 h-5 accent-[var(--uh-brand)]" @checked(setting('captcha.form.'.$k, in_array($k, ['login', 'register', 'password'], true) ? '1' : '0') === '1')>{{ $l }}</label>
            @endforeach
        </fieldset>
        <div class="text-xs text-muted leading-relaxed">
            <strong>Cloudflare Turnstile</strong>: dash.cloudflare.com › Turnstile › Add widget (free, add your domain). ·
            <strong>Google reCAPTCHA</strong>: google.com/recaptcha/admin › create (choose v2 "I'm not a robot" or v3), add your domain (and <code>localhost</code> for testing).
            Keys of one kind don't work with the other.
        </div>

        <div class="settings-subpanel">
            <p class="settings-subpanel-title">Cloudflare proxy (orange cloud)</p>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="cloudflare" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('security.cloudflare') === '1')>
                <span>My domain goes through Cloudflare — use the visitor's real IP (<code>CF-Connecting-IP</code>) for rate limits, logs and trusted devices.</span></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="cloudflare_only" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('security.cloudflare_only') === '1')>
                <span>Block visitors who skip Cloudflare and open the server IP directly. <span class="text-muted">(Only turns on when you are connected through Cloudflare right now, so you can't lock yourself out.)</span></span></label>
            <span class="text-xs {{ $via ? 'text-ok' : 'text-muted' }}">This page reached the server {{ $via ? 'through Cloudflare ✓' : 'directly (not through Cloudflare)' }} · your IP: {{ request()->ip() }}</span>
            <span class="text-xs text-muted">Also recommended in Cloudflare: SSL/TLS = Full (strict), "Always Use HTTPS", Bot Fight Mode, and a WAF rate-limit rule for <code>/login</code> and <code>/api/*</code>.</span>
        </div>
        @php
            $geo = \App\Support\GeoLocale::detect(request());
            $staffNo2fa = \App\Models\User::where('role', 'admin')->whereNull('two_factor_confirmed_at')->count();
        @endphp
        <div class="settings-subpanel">
            <p class="settings-subpanel-title">Login limits &amp; waiting time</p>
            <div class="grid gap-3 grid-cols-[repeat(auto-fit,minmax(min(180px,100%),1fr))]">
                <div>
                    <label for="login_max" class="label text-xs">Wrong tries before waiting</label>
                    <input id="login_max" name="login_max" type="number" min="3" max="20" value="{{ old('login_max', setting('security.login_max', 5)) }}" class="input h-10">
                    <x-field-error name="login_max" />
                </div>
                <div>
                    <label for="login_wait" class="label text-xs">Waiting time (minutes)</label>
                    <input id="login_wait" name="login_wait" type="number" min="1" max="1440" value="{{ old('login_wait', setting('security.login_wait', 15)) }}" class="input h-10">
                    <x-field-error name="login_wait" />
                </div>
                <div>
                    <label for="login_ip_max" class="label text-xs">Max wrong tries per IP (all accounts, 15 min)</label>
                    <input id="login_ip_max" name="login_ip_max" type="number" min="10" max="200" value="{{ old('login_ip_max', setting('security.login_ip_max', 30)) }}" class="input h-10">
                    <x-field-error name="login_ip_max" />
                </div>
            </div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="login_progressive" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('security.login_progressive', '1') === '1')>
                <span>Longer each time — every new lock doubles the wait (15 → 30 → 60 min …, max 24 hours). Resets after a day without locks.</span></label>
            <span class="text-xs text-muted">Also used for the 6-digit code step (6 tries). Users see a live countdown on the login page. Locks are written to the Activity log (<code>login.locked</code>).</span>
        </div>

        <div class="settings-subpanel">
            <p class="settings-subpanel-title">Staff accounts</p>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="staff_2fa" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('security.staff_2fa') === '1')>
                <span>Staff must use Google Authenticator to open the admin panel (strongly recommended — staff can approve top-ups and change wallets). <span class="text-muted">(Only turns on if your own account already has it.)</span></span></label>
            <span class="text-xs {{ $staffNo2fa ? 'text-warn' : 'text-ok' }}">{{ $staffNo2fa ? $staffNo2fa.' staff account(s) without Google Authenticator.' : 'Every staff account uses Google Authenticator ✓' }}</span>
        </div>
        <div class="settings-subpanel">
            <p class="settings-subpanel-title">Visitor location → language &amp; payment</p>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="geo_locale" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('geo.auto_locale', '1') === '1')>
                <span>Open the site in the visitor's language by location (Myanmar → မြန်မာ, Thailand → ไทย, China/HK/TW → 中文, Laos → ລາວ, Vietnam → Tiếng Việt, others → English). A language the visitor picks always wins.</span></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="geo_payment" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('geo.auto_payment', '1') === '1')>
                <span>Show the payment methods of the visitor's country on Home and Add Funds. A country saved in the profile always wins.</span></label>
            <span class="text-xs text-muted">Location comes from Cloudflare (<code>CF-IPCountry</code> — most accurate, turn on "IP Geolocation" in Cloudflare › Network), the server's GeoIP if your host provides it, or the browser language. No outside service is called.</span>
            <span class="text-xs {{ $geo ? 'text-ok' : 'text-muted' }}">Your location as the site sees it: {{ $geo ?: 'unknown' }}{{ $geo && config('unlockhub.countries.'.$geo) ? ' ('.config('unlockhub.countries.'.$geo).')' : '' }}</span>
        </div>
        <div class="settings-actions"><button class="btn btn-primary">Save security settings</button></div>
    </form>

    @if ($cp !== 'none' && \App\Services\Captcha::siteKey())
        <form method="POST" action="{{ route('admin.settings.security.test') }}" class="flex flex-col gap-2 pt-3 border-t border-line">
            @csrf
            <span class="text-sm font-semibold">Test the bot check {{ \App\Services\Captcha::active() ? '(passed '.\Illuminate\Support\Carbon::parse(setting('captcha.verified_at'))->diffForHumans().')' : '— required before it is switched on' }}</span>
            <x-captcha form="test" :force="true" />
            <button class="btn self-start">Run test</button>
        </form>
    @endif
</section>
