<?php

namespace App\Listeners;

use App\Models\Setting;
use App\Notifications\UserRegistrationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Notifications\AnonymousNotifiable;

class SendUserRegistrationNotification
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $user = $event->user;
        
        // Get admin notification emails from settings
        $adminEmails = Setting::getAdminNotificationEmails();
        
        // Only send if emails are configured
        if (empty($adminEmails)) {
            return;
        }

        // Send notification to each admin email
        foreach ($adminEmails as $email) {
            (new AnonymousNotifiable)
                ->route('mail', $email)
                ->notify(new UserRegistrationNotification($user));
        }
    }
}

