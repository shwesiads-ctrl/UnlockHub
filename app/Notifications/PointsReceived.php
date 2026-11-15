<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** In-site only: referral bonus or admin gift. */
class PointsReceived extends Notification
{
    use Queueable;

    public function __construct(public int $points, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => '+'.number_format($this->points).' points',
            'body' => $this->reason,
            'url' => route('account.points'),
            'tone' => 'success',
        ];
    }
}
