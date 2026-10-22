<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSetupLinkNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $token,
        private readonly CarbonImmutable $expiresAt,
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
        $setupPath = route('account-setup.show', ['token' => $this->token], false);
        $roleLabel = match ($notifiable->role ?? null) {
            'discipline_coordinator' => 'Discipline Coordinator',
            'discipline_officer' => 'Discipline Officer',
            'reporting_user' => 'Reporting User',
            'guidance_officer' => 'Guidance Officer',
            'us_supervisor' => 'US Supervisor',
            'student' => 'Student',
            default => 'PRISM User',
        };

        $viewData = [
            'recipientName' => trim((string) ($notifiable->first_name ?? '')) ?: 'PRISM User',
            'email' => (string) ($notifiable->email ?? ''),
            'roleLabel' => $roleLabel,
            'isStudent' => ($notifiable->role ?? null) === 'student',
            'setupUrl' => $publicUrl.'/'.ltrim($setupPath, '/'),
            'expiresAt' => $this->expiresAt,
            'logoUrl' => $publicUrl.'/assets/logo.png',
            'fontRegularUrl' => $publicUrl.'/email-assets/fonts/poppins/regular',
            'fontMediumUrl' => $publicUrl.'/email-assets/fonts/poppins/medium',
            'fontSemiBoldUrl' => $publicUrl.'/email-assets/fonts/poppins/semibold',
            'fontBoldUrl' => $publicUrl.'/email-assets/fonts/poppins/bold',
            'year' => now()->year,
        ];

        return (new MailMessage)
            ->subject('Activate your NU Clark PRISM account')
            ->view([
                'html' => 'emails.account-setup-link',
                'text' => 'emails.account-setup-link-text',
            ], $viewData);
    }
}
