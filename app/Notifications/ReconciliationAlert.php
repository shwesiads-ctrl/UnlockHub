<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReconciliationAlert extends Notification
{
    use Queueable;

    public function __construct(public int $new, public int $open) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Wallet reconciliation: {$this->new} new issue(s)",
            'body' => "{$this->open} open in total. Review before correcting anything.",
            'url' => route('admin.reconcile.index'),
            'tone' => 'rejected',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Wallet reconciliation: {$this->new} new issue(s)")
            ->line("The daily check found {$this->new} new difference(s); {$this->open} are open.")
            ->line('Balances are not changed automatically. Review and approve corrections in the admin panel.')
            ->action('Open reconciliation', route('admin.reconcile.index'));
    }
}
