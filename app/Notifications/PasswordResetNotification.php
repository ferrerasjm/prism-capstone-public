<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $publicUrl = rtrim(
            (string) config('app.public_url', config('app.url', 'http://localhost')),
            '/',
        );
        $resetPath = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);
        $passwordBroker = (string) config('auth.defaults.passwords');

        $viewData = [
            'recipientName' => trim((string) ($notifiable->first_name ?? '')) ?: 'PRISM User',
            'email' => (string) $notifiable->getEmailForPasswordReset(),
            'resetUrl' => $publicUrl.'/'.ltrim($resetPath, '/'),
            'expiryMinutes' => (int) config("auth.passwords.{$passwordBroker}.expire", 60),
            'logoUrl' => $publicUrl.'/assets/logo.png',
            'fontRegularUrl' => $publicUrl.'/email-assets/fonts/poppins/regular',
            'fontMediumUrl' => $publicUrl.'/email-assets/fonts/poppins/medium',
            'fontSemiBoldUrl' => $publicUrl.'/email-assets/fonts/poppins/semibold',
            'fontBoldUrl' => $publicUrl.'/email-assets/fonts/poppins/bold',
            'year' => now()->year,
        ];

        return (new MailMessage)
            ->subject('Reset your NU Clark PRISM password')
            ->view([
                'html' => 'emails.password-reset',
                'text' => 'emails.password-reset-text',
            ], $viewData);
    }
}
