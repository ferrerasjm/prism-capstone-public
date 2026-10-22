<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\UpccReferral;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UpccDecisionAvailableToComplainantNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly UpccReferral $referral,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $case = $this->referral->case;
        $report = $case?->incidentReport;

        return [
            'kind' => 'upcc_decision_available_complainant',
            'title' => 'UPCC decision available',
            'message' => "A decision has been reached on case {$case?->case_number}, related to your report {$report?->report_number}. "
                ."The appeal window closes on {$this->referral->appeal_window_end?->format('F d, Y')}.",
            'case_id' => $case?->id,
            'case_number' => $case?->case_number,
            'incident_report_id' => $report?->id,
            'referral_id' => $this->referral->id,
            'appeal_deadline' => $this->referral->appeal_window_end?->toDateString(),
            'action_label' => 'View Report',
            'url' => $this->urlFor($notifiable, $report),
        ];
    }

    /**
     * The complainant who filed the underlying report isn't always a reporting_user
     * account — officers and students can also file reports (direct encoding, student
     * complaints) — so the link has to match whichever role actually receives this.
     */
    private function urlFor(object $notifiable, ?\App\Models\IncidentReport $report): string
    {
        if (! $report) {
            return match ($notifiable->role ?? null) {
                'discipline_officer' => route('officer.dashboard', [], false),
                'student' => route('student.dashboard', [], false),
                default => route('reporter.dashboard', [], false),
            };
        }

        return match ($notifiable->role ?? null) {
            'discipline_officer' => route('officer.incidents.show', $report, false),
            'student' => route('student.complaints.show', $report, false),
            default => route('reporter.reports.show', $report, false),
        };
    }
}
