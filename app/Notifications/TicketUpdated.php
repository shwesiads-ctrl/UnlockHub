<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Support replied to (or changed the status of) the customer's ticket. */
class TicketUpdated extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket, public ?string $reply = null) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notify_email && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Ticket #{$this->ticket->ticket_no}: ".($this->reply ? 'new reply' : $this->ticket->statusLabel()),
            'body' => $this->reply ? Str::limit($this->reply, 140) : $this->ticket->subject,
            'url' => route('account.tickets.show', $this->ticket),
            'tone' => $this->ticket->status === 'resolved' ? 'success' : 'info',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = (string) setting('site.name', config('app.name'));
        $mail = (new MailMessage)
            ->subject("[#{$this->ticket->ticket_no}] {$this->ticket->subject}")
            ->greeting("Hello {$notifiable->name},");

        if ($this->reply) {
            $mail->line('Support replied to your ticket:')->line(Str::limit($this->reply, 1000));
        }

        return $mail->line('Status: **'.$this->ticket->statusLabel().'**')
            ->action('View ticket', route('account.tickets.show', $this->ticket))
            ->salutation("— {$site}");
    }
}
