<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class UserRegistrationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public User $user
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->user->fresh() ?? $this->user;
        $approveUrl = URL::signedRoute('admin.users.approve-from-email', ['user' => $user->id]);
        $rejectUrl = URL::signedRoute('admin.users.reject-from-email', ['user' => $user->id]);

        return (new MailMessage)
            ->subject(__('emails.user_registration_notification'))
            ->view('emails.user-registration-notification', [
                'user' => $user,
                'howHeardAbout' => $user->how_heard_about,
                'approveUrl' => $approveUrl,
                'rejectUrl' => $rejectUrl,
            ]);
    }
}

