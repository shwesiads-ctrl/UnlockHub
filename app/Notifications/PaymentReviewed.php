<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A top-up receipt was approved (wallet credited) or rejected (with reason). */
class PaymentReviewed extends Notification
{
    use Queueable;

    public function __construct(public PaymentRequest $request) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notify_email ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->request;
        $ok = $r->status === PaymentRequest::APPROVED;

        return [
            'title' => $ok ? 'Top-up approved: '.money($r->credited_amount) : 'Top-up rejected',
            'body' => $ok
                ? 'Added to your wallet via '.$r->method?->name.'.'
                : 'Reason: '.$r->reject_reason.'. You can upload the correct receipt again.',
            'url' => $ok ? route('account.statement') : route('account.funds.index'),
            'tone' => $ok ? 'success' : 'rejected',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;
        $site = (string) setting('site.name', config('app.name'));

        if ($r->status === PaymentRequest::APPROVED) {
            return (new MailMessage)
                ->subject('Top-up approved — '.money($r->credited_amount).' added')
                ->greeting("Hello {$notifiable->name},")
                ->line('Your top-up via '.$r->method?->name." (Txn {$r->transaction_id}) was approved.")
                ->line('**'.money($r->credited_amount).'** has been added to your wallet.')
                ->action('Place an order', route('services.index'))
                ->salutation("— {$site}");
        }

        return (new MailMessage)
            ->subject('Top-up rejected')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your top-up via '.$r->method?->name." (Txn {$r->transaction_id}) was not approved.")
            ->line('Reason: '.$r->reject_reason)
            ->action('Upload again', route('account.funds.index'))
            ->salutation("— {$site}");
    }
}
