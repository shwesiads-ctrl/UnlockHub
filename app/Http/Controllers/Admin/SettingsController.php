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

/** Site settings. Step 9: Telegram admin alerts. */
class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'telegramConfigured' => (bool) Notify::telegramToken(),
            'chatId' => Notify::telegramChatId(),
            'events' => Notify::TELEGRAM_EVENTS,
            'lowBalance' => setting('telegram.low_balance', 20),
            'mailDriver' => config('mail.default'),
        ]);
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
