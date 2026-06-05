<?php

namespace App\Listeners;

use App\Events\UserApproved;
use App\Notifications\UserApprovedNotification;

class SendUserApprovedNotification
{
    /**
     * Handle the event.
     */
    public function handle(UserApproved $event): void
    {
        $event->user->notify(new UserApprovedNotification($event->user));
    }
}

