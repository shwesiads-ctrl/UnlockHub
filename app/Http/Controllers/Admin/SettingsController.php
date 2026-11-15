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
