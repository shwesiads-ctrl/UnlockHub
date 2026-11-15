<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code, public string $purpose = 'login') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = (string) setting('site.name', config('app.name'));
        $minutes = (int) config('unlockhub.otp.ttl_minutes', 10);

        return (new MailMessage)
            ->subject("{$this->code} is your {$site} verification code")
            ->greeting("Hello {$notifiable->name},")
            ->line('Use this code to finish signing in:')
            ->line("**{$this->code}**")
            ->line("It expires in {$minutes} minutes. If you did not try to sign in, change your password.");
    }
}
