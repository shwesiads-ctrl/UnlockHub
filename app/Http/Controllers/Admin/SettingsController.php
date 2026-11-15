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

        return back()->with('success', 'Settings saved.');
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

        return back()->with('success', 'Social login settings saved.');
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

                return back()->with('error', 'Could not read that image (PNG or JPG, at least 192×192). The PHP GD extension is needed.');
            }
        }

        ActivityLog::record('settings.pwa', null, ['push' => $request->boolean('push_enabled'), 'icon' => $request->hasFile('icon')]);

        return back()->with('success', 'App settings saved.');
    }

    public function pushKeys(): RedirectResponse
    {
        \App\Services\WebPush::generateKeys();
        Setting::put('push.enabled', '1');
        ActivityLog::record('settings.push_keys');

        return back()->with('success', 'Push keys created and push turned on. Customers can now enable notifications on their phones.');
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

        return back()->with('success', 'Statement settings saved.');
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

        return back()->with('success', 'Telegram settings saved.');
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
