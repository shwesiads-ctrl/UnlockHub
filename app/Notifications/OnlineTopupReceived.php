<?php

namespace App\Notifications;

use App\Models\OnlinePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnlineTopupReceived extends Notification
{
    use Queueable;

    public function __construct(public OnlinePayment $payment) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notify_email && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Top-up received: '.money($this->payment->credit_ks),
            'body' => 'Paid '.number_format((float) $this->payment->amount, 2).' '.$this->payment->currency.' via '.OnlinePayment::GATEWAYS[$this->payment->gateway].'.',
            'url' => route('account.statement'),
            'tone' => 'success',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Top-up received — '.money($this->payment->credit_ks).' added')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your payment of '.number_format((float) $this->payment->amount, 2).' '.$this->payment->currency.' via '.OnlinePayment::GATEWAYS[$this->payment->gateway].' was confirmed.')
            ->line('**'.money($this->payment->credit_ks).'** has been added to your wallet.')
            ->action('Place an order', route('services.index'));
    }
}
