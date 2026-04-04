<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    /**
     * Get the reset URL for the given notifiable.
     */
    protected function resetUrl(mixed $notifiable): string
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $locale = app()->getLocale();
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'en';

        return $frontendUrl . '/' . $locale . '/auth/reset-password?'
            . http_build_query([
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
    }
}
