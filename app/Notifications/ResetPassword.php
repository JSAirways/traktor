<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends ResetPasswordNotification
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        // Ensure locale is set for the notification
        $locale = $notifiable->preferredLocale() ?? app()->getLocale();
        app()->setLocale($locale);
        
        // Get base reset URL
        $url = $this->resetUrl($notifiable);
        
        // Add locale parameter to URL to ensure page opens in correct language
        $separator = strpos($url, '?') !== false ? '&' : '?';
        $url = $url . $separator . 'locale=' . urlencode($locale);
        
        return (new MailMessage)
            ->subject(__('emails.reset_password_notification'))
            ->view('emails.reset-password', [
                'url' => $url,
                'user' => $notifiable,
                'expire' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }
}

