<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Every item of a bulk order is finished — one summary instead of many emails. */
class BatchFinished extends Notification
{
    use Queueable;

    public function __construct(
        public string $batchId,
        public string $serviceName,
        public int $success,
        public int $rejected,
        public float $refunded,
        public string $priceCurrency = 'ks',
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notify_email ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Bulk order finished: {$this->success} done, {$this->rejected} rejected",
            'body' => $this->serviceName.($this->refunded > 0 ? ' · '.unit_price($this->refunded, $this->priceCurrency).($this->priceCurrency === 'points' ? ' returned' : ' refunded') : ''),
            'url' => route('account.orders.index', ['batch' => $this->batchId]),
            'tone' => $this->rejected ? 'pending' : 'success',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = (string) setting('site.name', config('app.name'));

        return (new MailMessage)
            ->subject("Bulk order finished — {$this->serviceName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your bulk order for **{$this->serviceName}** is finished.")
            ->line("Completed: **{$this->success}** · Rejected: **{$this->rejected}**")
            ->lineIf($this->refunded > 0, $this->priceCurrency === 'points' ? unit_price($this->refunded, 'points').' were returned to your points balance for rejected items.' : money($this->refunded).' was refunded to your wallet for rejected items.')
            ->action('See results / download CSV', route('account.orders.index', ['batch' => $this->batchId]))
            ->salutation("— {$site}");
    }
}
