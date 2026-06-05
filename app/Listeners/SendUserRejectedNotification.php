<?php

namespace App\Listeners;

use App\Events\UserRejected;
use App\Notifications\UserRejectedNotification;

class SendUserRejectedNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserRejected $event): void
    {
        $event->user->notify(new UserRejectedNotification($event->user, $event->reason));
    }
}

