<?php

namespace App\Support;

use App\Jobs\SendTelegramMessage;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Fire-and-forget notifications.
 * Sent only after the surrounding DB transaction commits, and a mail/Telegram
 * failure never breaks the order or payment that triggered it.
 */
class Notify
{
    public static function user(?User $user, Notification $notification): void
    {
        if (! $user) {
            return;
        }

        DB::afterCommit(function () use ($user, $notification) {
            try {
                $user->notify($notification);

                // Phone / browser push (if this user turned it on)
                if (\App\Services\WebPush::enabled() && \App\Models\PushSubscription::where('user_id', $user->id)->exists()) {
                    \App\Jobs\SendWebPush::dispatch($user->id);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /** Admin alert on Telegram, if configured and this event is switched on. */
    public static function admin(string $event, string $html): void
    {
        if (! self::telegramEnabled($event)) {
            return;
        }

        DB::afterCommit(function () use ($html) {
            try {
                SendTelegramMessage::dispatchAfterResponse($html);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public const TELEGRAM_EVENTS = [
        'payment' => 'New top-up receipt waiting',
        'manual_order' => 'New manual order',
        'api_failed' => 'API order failed → manual queue',
        'low_balance' => 'Supplier balance low',
        'testimonial' => 'New review to approve',
        'contact' => 'Contact form message',
    ];

    public static function telegramToken(): ?string
    {
        $stored = setting('telegram.bot_token');

        if ($stored) {
            try {
                return decrypt($stored);
            } catch (Throwable) {
                return null;
            }
        }

        return config('unlockhub.telegram.bot_token') ?: null;
    }

    public static function telegramChatId(): ?string
    {
        return setting('telegram.chat_id') ?: (config('unlockhub.telegram.admin_chat_id') ?: null);
    }

    public static function telegramEnabled(string $event): bool
    {
        return self::telegramToken() && self::telegramChatId()
            && setting('telegram.event.'.$event, '1') === '1';
    }

    /** Escape text for Telegram HTML parse mode. */
    public static function e(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
