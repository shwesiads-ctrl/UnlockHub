<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WebPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWebPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $userId) {}

    public function handle(WebPush $push): void
    {
        if ($user = User::find($this->userId)) {
            $push->toUser($user);
        }
    }
}
