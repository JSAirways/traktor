<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends VerifyEmailNotification
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
        
        // Get base verification URL
        $verificationUrl = $this->verificationUrl($notifiable);
        
        // Add locale parameter to URL to ensure page opens in correct language
        $separator = strpos($verificationUrl, '?') !== false ? '&' : '?';
        $verificationUrl = $verificationUrl . $separator . 'locale=' . urlencode($locale);
        
        return (new MailMessage)
            ->subject(__('emails.verify_email_address'))
            ->view('emails.verify-email', [
                'url' => $verificationUrl,
                'user' => $notifiable,
            ]);
    }
}

