{{-- CAPTCHA (Google reCAPTCHA / Cloudflare Turnstile) and Cloudflare proxy --}}
@php
    $cp = \App\Services\Captcha::provider();
    $via = request()->attributes->get('via_cloudflare') === true;
@endphp
<section id="security" class="card p-6 flex flex-col gap-4">
    <div class="flex justify-between items-start gap-3 flex-wrap">
        <div>
            <h2 class="m-0 text-lg font-bold">Security — bot check &amp; Cloudflare</h2>
            <p class="m-0 text-sm text-muted">Stop bots on login, register and other forms with Google reCAPTCHA or Cloudflare Turnstile.</p>
        </div>
        @if (\App\Services\Captcha::active())
            <span class="badge badge-success">Active ✓ {{ \App\Services\Captcha::PROVIDERS[$cp] }}</span>
        @elseif ($cp !== 'none')
            <span class="badge badge-pending">Saved — not active until tested</span>
        @else
            <span class="badge bg-surface-2 text-muted">Off</span>
        @endif
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

        <div class="rounded-xl border border-line p-4 flex flex-col gap-2">
            <span class="font-semibold">Cloudflare proxy (orange cloud)</span>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="cloudflare" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('security.cloudflare') === '1')>
                <span>My domain goes through Cloudflare — use the visitor's real IP (<code>CF-Connecting-IP</code>) for rate limits, logs and trusted devices.</span></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="cloudflare_only" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('security.cloudflare_only') === '1')>
                <span>Block visitors who skip Cloudflare and open the server IP directly. <span class="text-muted">(Only turns on when you are connected through Cloudflare right now, so you can't lock yourself out.)</span></span></label>
            <span class="text-xs {{ $via ? 'text-ok' : 'text-muted' }}">This page reached the server {{ $via ? 'through Cloudflare ✓' : 'directly (not through Cloudflare)' }} · your IP: {{ request()->ip() }}</span>
            <span class="text-xs text-muted">Also recommended in Cloudflare: SSL/TLS = Full (strict), "Always Use HTTPS", Bot Fight Mode, and a WAF rate-limit rule for <code>/login</code> and <code>/api/*</code>.</span>
        </div>
        @php $geo = \App\Support\GeoLocale::detect(request()); @endphp
        <div class="rounded-xl border border-line p-4 flex flex-col gap-2">
            <span class="font-semibold">Visitor location → language &amp; payment</span>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="geo_locale" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('geo.auto_locale', '1') === '1')>
                <span>Open the site in the visitor's language by location (Myanmar → မြန်မာ, Thailand → ไทย, China/HK/TW → 中文, Laos → ລາວ, Vietnam → Tiếng Việt, others → English). A language the visitor picks always wins.</span></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="geo_payment" value="1" class="w-5 h-5 mt-0.5 accent-[var(--uh-brand)]" @checked(setting('geo.auto_payment', '1') === '1')>
                <span>Show the payment methods of the visitor's country on Home and Add Funds. A country saved in the profile always wins.</span></label>
            <span class="text-xs text-muted">Location comes from Cloudflare (<code>CF-IPCountry</code> — most accurate, turn on "IP Geolocation" in Cloudflare › Network), the server's GeoIP if your host provides it, or the browser language. No outside service is called.</span>
            <span class="text-xs {{ $geo ? 'text-ok' : 'text-muted' }}">Your location as the site sees it: {{ $geo ?: 'unknown' }}{{ $geo && config('unlockhub.countries.'.$geo) ? ' ('.config('unlockhub.countries.'.$geo).')' : '' }}</span>
        </div>
        <button class="btn btn-primary self-start">Save security settings</button>
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
