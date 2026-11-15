<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** An order finished: success (with result) or rejected (with refund). */
class OrderUpdated extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        // Bulk items: one summary email per batch instead (BatchFinished)
        $mail = $notifiable->notify_email && ! $this->order->batch_id;

        return $mail ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $o = $this->order;
        $ok = $o->status === Order::SUCCESS;

        return [
            'title' => $ok ? "Order #{$o->order_no} completed" : "Order #{$o->order_no} rejected",
            'body' => $ok
                ? $o->service_name.($o->imei ? ' · '.$o->imei : '').' — result is ready.'
                : $o->service_name.' — '.Str::limit((string) $o->result, 120).' '.money($o->price).' refunded.',
            'url' => route('account.orders.show', $o),
            'tone' => $ok ? 'success' : 'rejected',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order;
        $site = (string) setting('site.name', config('app.name'));

        if ($o->status === Order::SUCCESS) {
            return (new MailMessage)
                ->subject("✓ Order #{$o->order_no} completed — {$o->service_name}")
                ->greeting("Hello {$notifiable->name},")
                ->line("Your order **{$o->service_name}**".($o->imei ? " for **{$o->imei}**" : '').' is complete.')
                ->line('**Result:**')
                ->line(Str::limit((string) $o->result, 2000))
                ->action('View order', route('account.orders.show', $o))
                ->lineIf($o->points > 0, "You earned +{$o->points} points.")
                ->salutation("— {$site}");
        }

        return (new MailMessage)
            ->subject("Order #{$o->order_no} rejected — refunded")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your order **{$o->service_name}**".($o->imei ? " for **{$o->imei}**" : '').' could not be completed.')
            ->line('Reason: '.Str::limit((string) $o->result, 500))
            ->line(money($o->price).' has been refunded to your wallet.')
            ->action('View order', route('account.orders.show', $o))
            ->salutation("— {$site}");
    }
}
