<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $otp,
        private readonly int $expiryMinutes,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function viaConnections(): array
    {
        return [
            'mail' => (string) config('queue.default', 'database'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $publicUrl = rtrim(
            (string) config('app.public_url', config('app.url', 'http://localhost')),
            '/',
        );
        $viewData = [
            'recipientName' => trim((string) ($notifiable->first_name ?? '')) ?: 'PRISM User',
            'email' => (string) ($notifiable->email ?? ''),
            'otp' => $this->otp,
            'expiryMinutes' => $this->expiryMinutes,
            'logoUrl' => $publicUrl.'/assets/logo.png',
            'fontRegularUrl' => $publicUrl.'/email-assets/fonts/poppins/regular',
            'fontMediumUrl' => $publicUrl.'/email-assets/fonts/poppins/medium',
            'fontSemiBoldUrl' => $publicUrl.'/email-assets/fonts/poppins/semibold',
            'fontBoldUrl' => $publicUrl.'/email-assets/fonts/poppins/bold',
            'year' => now()->year,
        ];

        return (new MailMessage)
            ->subject('Your NU Clark PRISM login verification code')
            ->view([
                'html' => 'emails.login-otp',
                'text' => 'emails.login-otp-text',
            ], $viewData);
    }
}
