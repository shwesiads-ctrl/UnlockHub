<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

/** Site settings: general info, contact, social links, Telegram admin alerts. */
class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'general' => collect(self::GENERAL)->mapWithKeys(fn ($rule, $key) => [$key => setting($key)])->all(),
            'kbPages' => \App\Models\Post::where('type', 'kb')->orderBy('title')->get(['title', 'slug']),
            'socialProviders' => \App\Services\Social\SocialProviders::PROVIDERS,
            'telegramConfigured' => (bool) Notify::telegramToken(),
            'chatId' => Notify::telegramChatId(),
            'events' => Notify::TELEGRAM_EVENTS,
            'lowBalance' => setting('telegram.low_balance', 20),
            'mailDriver' => config('mail.default'),
        ]);
    }

    /** setting key => validation rules */
    public const GENERAL = [
        'site.name' => ['required', 'string', 'max:60'],
        'site.support_hours' => ['nullable', 'string', 'max:60'],
        'contact.email' => ['nullable', 'email', 'max:255'],
        'contact.phone' => ['nullable', 'string', 'max:40'],
        'contact.address' => ['nullable', 'string', 'max:255'],
        'social.telegram' => ['nullable', 'url:http,https', 'max:255'],
        'social.viber' => ['nullable', 'string', 'max:255'],
        'social.facebook' => ['nullable', 'url:http,https', 'max:255'],
        'social.youtube' => ['nullable', 'url:http,https', 'max:255'],
        'currency.usd_rate' => ['nullable', 'numeric', 'min:0'],
        'pages.terms_slug' => ['nullable', 'string', 'exists:posts,slug'],
    ];

    public function updateGeneral(Request $request): RedirectResponse
    {
        // Form field names use _ instead of . (site_name → site.name)
        $rules = [];
        foreach (self::GENERAL as $key => $rule) {
            $rules[str_replace('.', '_', $key)] = $rule;
        }
        $rules['logo'] = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024']; // no SVG: it can carry scripts
        $rules['favicon'] = ['nullable', 'file', 'mimes:png,ico', 'max:256'];
        $data = $request->validate($rules);

        foreach (array_keys(self::GENERAL) as $key) {
            $value = $data[str_replace('.', '_', $key)] ?? null;
            Setting::put($key, $value === null ? null : (string) $value);
        }
        // Branding
        foreach (['logo' => 'site.logo', 'favicon' => 'site.favicon'] as $field => $key) {
            if ($request->boolean("remove_{$field}") && ($old = setting($key))) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
                Setting::put($key, null);
            }
            if ($request->hasFile($field)) {
                if ($old = setting($key)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
                }
                Setting::put($key, $request->file($field)->store('branding', 'public'));
            }
        }
        Setting::put('site.logo_show_name', $request->boolean('logo_show_name') ? '1' : '0');

        ActivityLog::record('settings.general_updated');

        return redirect()->to(route('admin.settings.index').'#site-settings')->with('success', 'Settings saved.');
    }

    public function updateHomePage(Request $request): RedirectResponse
    {
        $textRules = [
            'hero_badge' => ['nullable', 'string', 'max:100'],
            'hero_title' => ['required', 'string', 'max:180'],
            'hero_description' => ['required', 'string', 'max:700'],
            'browse_label' => ['required', 'string', 'max:50'],
            'reseller_cta_label' => ['required', 'string', 'max:50'],
            'trust_1' => ['nullable', 'string', 'max:80'],
            'trust_2' => ['nullable', 'string', 'max:80'],
            'trust_3' => ['nullable', 'string', 'max:80'],
            'categories_title' => ['required', 'string', 'max:80'],
            'category_imei_title' => ['required', 'string', 'max:100'],
            'category_imei_description' => ['required', 'string', 'max:300'],
            'category_server_title' => ['required', 'string', 'max:100'],
            'category_server_description' => ['required', 'string', 'max:300'],
            'category_remote_title' => ['required', 'string', 'max:100'],
            'category_remote_description' => ['required', 'string', 'max:300'],
            'hot_title' => ['required', 'string', 'max:80'],
            'how_title' => ['required', 'string', 'max:80'],
            'step_1_title' => ['required', 'string', 'max:80'],
            'step_1_description' => ['required', 'string', 'max:220'],
            'step_2_title' => ['required', 'string', 'max:80'],
            'step_2_description' => ['required', 'string', 'max:220'],
            'step_3_title' => ['required', 'string', 'max:80'],
            'step_3_description' => ['required', 'string', 'max:220'],
            'step_4_title' => ['required', 'string', 'max:80'],
            'step_4_description' => ['required', 'string', 'max:220'],
            'payment_title' => ['nullable', 'string', 'max:100'],
            'payment_description' => ['required', 'string', 'max:240'],
        ];
        $data = $request->validate($textRules + [
            'show_reseller_cta' => ['boolean'],
            'show_categories' => ['boolean'],
            'show_hot_services' => ['boolean'],
            'show_how_it_works' => ['boolean'],
            'show_payment_banner' => ['boolean'],
        ]);

        foreach (array_keys($textRules) as $key) {
            Setting::put('home.'.$key, trim((string) ($data[$key] ?? '')));
        }
        foreach (['reseller_cta', 'categories', 'hot_services', 'how_it_works', 'payment_banner'] as $section) {
            $key = $section === 'reseller_cta' ? 'home.reseller_cta_enabled' : 'home.show_'.$section;
            Setting::put($key, $request->boolean('show_'.$section) ? '1' : '0');
        }

        ActivityLog::record('settings.home_page_updated');

        return redirect()->to(route('admin.settings.index').'#home-page')->with('success', 'Home page settings saved.');
    }

    public function updateSeo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:70'],
            'description' => ['nullable', 'string', 'max:300'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'google_analytics_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/i'],
        ]);

        foreach (['title', 'description', 'keywords', 'image_url', 'google_analytics_id'] as $key) {
            Setting::put('seo.'.$key, trim((string) ($data[$key] ?? '')));
        }
        Setting::put('seo.analytics_enabled', $request->boolean('analytics_enabled') ? '1' : '0');

        ActivityLog::record('settings.seo_updated');

        return redirect()->to(route('admin.settings.index').'#seo')->with('success', 'SEO and analytics settings saved.');
    }

    public function updateSocial(Request $request): RedirectResponse
    {
        $providers = array_keys(\App\Services\Social\SocialProviders::PROVIDERS);
        $rules = [];
        foreach ($providers as $p) {
            $rules["{$p}_enabled"] = ['boolean'];
            $rules["{$p}_client_id"] = ['nullable', 'string', 'max:255'];
            $rules["{$p}_client_secret"] = ['nullable', 'string', 'max:255'];
        }
        $rules['telegram_bot_username'] = ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9_]{5,}$/'];
        $rules['telegram_bot_token'] = ['nullable', 'string', 'max:100', 'regex:/^\d+:[A-Za-z0-9_-]{30,}$/'];
        $data = $request->validate($rules);

        foreach ($providers as $p) {
            Setting::put("social_login.{$p}.enabled", $request->boolean("{$p}_enabled") ? '1' : '0');
            $changed = $p === 'telegram'
                ? (($data['telegram_bot_username'] ?? null) !== setting('social_login.telegram.bot_username') || filled($data['telegram_bot_token'] ?? null))
                : (($data["{$p}_client_id"] ?? null) !== setting("social_login.{$p}.client_id") || filled($data["{$p}_client_secret"] ?? null));
            if ($changed) {
                Setting::put("social_login.{$p}.verified_at", null); // new credentials → test again
            }
            if ($p !== 'telegram') {
                Setting::put("social_login.{$p}.client_id", $data["{$p}_client_id"] ?? null);
                if (filled($data["{$p}_client_secret"] ?? null)) {
                    Setting::put("social_login.{$p}.client_secret", encrypt($data["{$p}_client_secret"]));
                }
            }
        }
        Setting::put('social_login.telegram.bot_username', $data['telegram_bot_username'] ?? null);
        if (filled($data['telegram_bot_token'] ?? null)) {
            Setting::put('social_login.telegram.bot_token', encrypt($data['telegram_bot_token']));
        }

        ActivityLog::record('settings.social_updated');

        return redirect()->to(route('admin.settings.index').'#social-login')->with('success', 'Social login settings saved.');
    }

    public function updateAccountDisplay(Request $request): RedirectResponse
    {
        Setting::put('account.show_balance', $request->boolean('show_balance') ? '1' : '0');
        Setting::put('account.show_points', $request->boolean('show_points') ? '1' : '0');

        ActivityLog::record('settings.account_display_updated', null, [
            'balance' => $request->boolean('show_balance'),
            'points' => $request->boolean('show_points'),
        ]);

        return redirect()->to(route('admin.settings.index').'#site-settings')->with('success', 'Account header display settings saved.');
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:'.implode(',', array_keys(\App\Services\Captcha::PROVIDERS))],
            'site_key' => ['nullable', 'string', 'max:200'],
            'secret' => ['nullable', 'string', 'max:200'],
            'min_score' => ['required', 'numeric', 'between:0.1,0.9'],
            'forms' => ['array'],
            'forms.*' => ['in:'.implode(',', array_keys(\App\Services\Captcha::FORMS))],
            'login_max' => ['required', 'integer', 'between:3,20'],
            'login_wait' => ['required', 'integer', 'between:1,1440'],
            'login_ip_max' => ['required', 'integer', 'between:10,200'],
        ]);

        Setting::put('security.login_max', (string) $data['login_max']);
        Setting::put('security.login_wait', (string) $data['login_wait']);
        Setting::put('security.login_ip_max', (string) $data['login_ip_max']);
        Setting::put('security.login_progressive', $request->boolean('login_progressive') ? '1' : '0');

        $changed = $data['provider'] !== setting('captcha.provider', 'none')
            || ($data['site_key'] ?? null) !== setting('captcha.site_key') || filled($data['secret'] ?? null);

        Setting::put('captcha.provider', $data['provider']);
        Setting::put('captcha.site_key', $data['site_key'] ?? null);
        if (filled($data['secret'] ?? null)) {
            Setting::put('captcha.secret', encrypt($data['secret']));
        }
        Setting::put('captcha.min_score', (string) $data['min_score']);
        foreach (array_keys(\App\Services\Captcha::FORMS) as $f) {
            Setting::put('captcha.form.'.$f, in_array($f, $data['forms'] ?? [], true) ? '1' : '0');
        }
        if ($changed) {
            Setting::put('captcha.verified_at', null); // not enforced until tested again
        }

        // Only switch on "staff must use 2FA" if the person saving has it, so they can't lock themselves out
        $staff2fa = $request->boolean('staff_2fa') && $request->user()->hasTwoFactor();
        Setting::put('security.staff_2fa', $staff2fa ? '1' : '0');

        Setting::put('geo.auto_locale', $request->boolean('geo_locale') ? '1' : '0');
        Setting::put('geo.auto_payment', $request->boolean('geo_payment') ? '1' : '0');

        $cfWas = setting('security.cloudflare');
        Setting::put('security.cloudflare', $request->boolean('cloudflare') ? '1' : '0');
        // "Cloudflare only" needs the proxy setting and must come through Cloudflare right now (no self-lockout)
        $cfOnly = $request->boolean('cloudflare_only') && $request->boolean('cloudflare')
            && $request->attributes->get('via_cloudflare') === true;
        Setting::put('security.cloudflare_only', $cfOnly ? '1' : '0');

        ActivityLog::record('settings.security', null, ['captcha' => $data['provider'], 'forms' => $data['forms'] ?? [], 'cloudflare' => $request->boolean('cloudflare'), 'cloudflare_only' => $cfOnly]);

        $msg = 'Security settings saved.';
        if ($data['provider'] !== 'none' && ! setting('captcha.verified_at')) {
            $msg .= ' The bot check is NOT active yet — pass the Test below to switch it on.';
        }
        if ($request->boolean('staff_2fa') && ! $staff2fa) {
            $msg .= ' "Staff must use Google Authenticator" was not turned on — set it up on your own account first.';
        }
        if ($request->boolean('cloudflare_only') && ! $cfOnly) {
            $msg .= ' "Cloudflare only" was not turned on because this request did not come through Cloudflare (it would lock you out).';
        }

        return redirect()->to(route('admin.settings.index').'#security')->with('success', $msg);
    }

    public function testCaptcha(Request $request): RedirectResponse
    {
        $back = redirect()->to(route('admin.settings.index').'#security');
        $provider = \App\Services\Captcha::provider();
        if ($provider === 'none' || ! \App\Services\Captcha::siteKey() || ! \App\Services\Captcha::secret()) {
            return $back->with('error', 'Choose a provider and save the site key and secret first.');
        }

        $r = \App\Services\Captcha::verify($request, $provider, (string) \App\Services\Captcha::secret(), 'test');
        if (! $r['ok']) {
            Setting::put('captcha.verified_at', null);

            return $back->with('error', 'Test failed: '.$r['message'].' Check that the site key and secret belong together and this domain is allowed.');
        }

        Setting::put('captcha.verified_at', now()->toDateTimeString());
        ActivityLog::record('settings.captcha_tested', null, ['provider' => $provider, 'hostname' => $r['raw']['hostname'] ?? null]);

        return $back->with('success', \App\Services\Captcha::PROVIDERS[$provider].' works ✓'.(isset($r['raw']['score']) ? ' (score '.$r['raw']['score'].')' : '').' — the bot check is now active on the forms you ticked.');
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['nullable', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:100'],
        ]);

        $changed = false;
        foreach (['host', 'port', 'encryption', 'username', 'from_address', 'from_name'] as $k) {
            $changed = $changed || (string) setting('mail.'.$k) !== (string) ($data[$k] ?? '');
            Setting::put('mail.'.$k, $data[$k] ?? null);
        }
        if (filled($data['password'] ?? null)) {
            Setting::put('mail.password', encrypt($data['password']));
            $changed = true;
        }
        if ($request->boolean('remove_password')) {
            Setting::put('mail.password', null);
            $changed = true;
        }
        if ($changed) {
            Setting::put('mail.verified_at', null);
        }
        ActivityLog::record('settings.mail', null, ['host' => $data['host'] ?? null]);

        return redirect()->to(route('admin.settings.index').'#smtp')->with('success', 'Email settings saved. Send a test email to confirm they work.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email']]);
        \App\Support\MailSettings::apply();
        $back = redirect()->to(route('admin.settings.index').'#smtp');

        try {
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from '.setting('site.name', config('app.name')).'. If you can read this, SMTP works.',
                fn ($m) => $m->to($data['to'])->subject('SMTP test ✓ — '.setting('site.name', config('app.name')))
            );
        } catch (\Throwable $e) {
            Setting::put('mail.verified_at', null);

            return $back->with('error', 'Sending failed: '.\Illuminate\Support\Str::limit($e->getMessage(), 300));
        }

        if (config('mail.default') !== 'smtp') {
            return $back->with('error', 'No SMTP host saved — the message only went to the log ('.config('mail.default').').');
        }

        Setting::put('mail.verified_at', now()->toDateTimeString());
        ActivityLog::record('settings.mail_tested', null, ['to' => $data['to']]);

        return $back->with('success', "Test email sent to {$data['to']} ✓ — check the inbox (and spam).");
    }

    /** App (PWA): name on the home screen, colour, icon, push notifications. */
    public function updatePwa(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'short_name' => ['nullable', 'string', 'max:20'],
            'theme_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048', 'dimensions:min_width=192,min_height=192'],
            'push_enabled' => ['boolean'],
        ]);

        Setting::put('pwa.short_name', $data['short_name'] ?? '');
        Setting::put('pwa.theme_color', $data['theme_color']);
        Setting::put('push.enabled', $request->boolean('push_enabled') && \App\Services\WebPush::publicKey() ? '1' : '0');

        if ($request->hasFile('icon')) {
            try {
                $this->makeIcons($request->file('icon')->getRealPath(), $data['theme_color']);
                Setting::put('pwa.icon_version', (string) time());
            } catch (\Throwable $e) {
                report($e);

                return redirect()->to(route('admin.settings.index').'#mobile-app')->with('error', 'Could not read that image (PNG or JPG, at least 192×192). The PHP GD extension is needed.');
            }
        }

        ActivityLog::record('settings.pwa', null, ['push' => $request->boolean('push_enabled'), 'icon' => $request->hasFile('icon')]);

        return redirect()->to(route('admin.settings.index').'#mobile-app')->with('success', 'App settings saved.');
    }

    public function pushKeys(): RedirectResponse
    {
        \App\Services\WebPush::generateKeys();
        Setting::put('push.enabled', '1');
        ActivityLog::record('settings.push_keys');

        return redirect()->to(route('admin.settings.index').'#mobile-app')->with('success', 'Push keys created and push turned on. Customers can now enable notifications on their phones.');
    }

    /** 192, 512, maskable 512 (logo on brand colour with safe padding) and the 180px Apple icon. */
    private function makeIcons(string $path, string $hex): void
    {
        $src = imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            throw new \RuntimeException('Not an image');
        }
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        foreach (['icon-192.png' => [192, 0, false], 'icon-512.png' => [512, 0, false], 'maskable-512.png' => [512, 0.2, true], 'apple-touch-icon.png' => [180, 0.08, true]] as $file => [$size, $pad, $bg]) {
            $dst = imagecreatetruecolor($size, $size);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, $bg ? imagecolorallocate($dst, $r, $g, $b) : imagecolorallocatealpha($dst, 0, 0, 0, 127));
            $inner = (int) round($size * (1 - 2 * $pad));
            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min($inner / $w, $inner / $h);
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            imagecopyresampled($dst, $src, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagepng($dst);
            $disk->put('pwa/'.$file, (string) ob_get_clean());
            imagedestroy($dst);
        }
        imagedestroy($src);
    }

    public function updateStatements(Request $request): RedirectResponse
    {
        Setting::put('statements.monthly', $request->boolean('monthly') ? '1' : '0');

        return redirect()->to(route('admin.settings.index').'#monthly-statements')->with('success', 'Statement settings saved.');
    }

    public function updateTelegram(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bot_token' => ['nullable', 'string', 'max:100', 'regex:/^\d+:[A-Za-z0-9_-]{30,}$/'],
            'chat_id' => ['nullable', 'string', 'max:40', 'regex:/^-?\d+$|^@[A-Za-z0-9_]{5,}$/'],
            'low_balance' => ['nullable', 'numeric', 'min:0'],
            'events' => ['array'],
            'remove_token' => ['boolean'],
        ], [
            'bot_token.regex' => 'That does not look like a bot token (it looks like 123456789:ABC…).',
            'chat_id.regex' => 'Chat ID is a number like 123456789 or -1001234567890 (groups), or @channelname.',
        ]);

        if ($request->boolean('remove_token')) {
            Setting::put('telegram.bot_token', null);
        } elseif (filled($data['bot_token'] ?? null)) {
            Setting::put('telegram.bot_token', encrypt($data['bot_token']));
        }

        Setting::put('telegram.chat_id', $data['chat_id'] ?? null);
        Setting::put('telegram.low_balance', (string) ($data['low_balance'] ?? 0));

        foreach (array_keys(Notify::TELEGRAM_EVENTS) as $event) {
            Setting::put('telegram.event.'.$event, in_array($event, $data['events'] ?? [], true) ? '1' : '0');
        }

        ActivityLog::record('settings.telegram_updated');

        return redirect()->to(route('admin.settings.index').'#telegram-alerts')->with('success', 'Telegram settings saved.');
    }

    /** Send a test message right now (not queued) and show Telegram's answer. */
    public function testTelegram(): RedirectResponse
    {
        $token = Notify::telegramToken();
        $chat = Notify::telegramChatId();

        if (! $token || ! $chat) {
            return back()->with('error', 'Save a bot token and chat ID first.');
        }

        try {
            $response = Http::timeout(10)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chat,
                'text' => '✅ <b>'.Notify::e((string) setting('site.name', config('app.name'))).'</b> admin alerts are working.',
                'parse_mode' => 'HTML',
            ]);
        } catch (Throwable $e) {
            return back()->with('error', 'Could not reach Telegram: '.$e->getMessage());
        }

        if ($response->successful() && $response->json('ok')) {
            return back()->with('success', 'Test message sent — check Telegram.');
        }

        $desc = $response->json('description') ?? ('HTTP '.$response->status());

        return back()->with('error', 'Telegram said: '.$desc.(str_contains((string) $desc, 'chat not found') ? ' — send /start to your bot first, or add it to the group.' : ''));
    }
}
