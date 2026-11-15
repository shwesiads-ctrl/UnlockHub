<?php

namespace App\Jobs;

use App\Support\Notify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public string $html) {}

    public function handle(): void
    {
        $token = Notify::telegramToken();
        $chat = Notify::telegramChatId();

        if (! $token || ! $chat) {
            return;
        }

        $response = Http::timeout(10)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chat,
            'text' => Str::limit($this->html, 3900),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 'true',
        ]);

        if ($response->failed()) {
            Log::warning('Telegram send failed', ['status' => $response->status(), 'body' => Str::limit($response->body(), 300)]);
        }
    }
}
