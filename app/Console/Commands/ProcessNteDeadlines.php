<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CaseNotice;
use App\Notifications\NteDeadlineReminderNotification;
use App\Notifications\NteOverdueNotification;
use App\Notifications\NteOverdueStudentNotification;
use Illuminate\Console\Command;

/**
 * Processes Notice to Explain deadlines: flags overdue notices and issues
 * the associated reminders. Scheduled daily; unprocessed overdue notices
 * are what `prism:automation-check` warns about.
 */
class ProcessNteDeadlines extends Command
{
    protected $signature = 'cases:process-nte-deadlines';

    protected $description = 'Send NTE deadline reminders and flag overdue responses';

    /**
     * Sends NTE deadline reminders and flags overdue responses.
     *
     * Considers only live notices - not voided, not yet acknowledged,
     * on cases still pending_nte with no explanation submitted - and
     * iterates lazily so the job stays flat in memory regardless of how
     * many notices are outstanding.
     */
    public function handle(): int
    {
        $reminders = 0;
        $overdue = 0;
        $today = today();

        $notices = CaseNotice::query()
            ->with(['case.student', 'case.assignedOfficer'])
            ->where('notice_type', 'notice_to_explain')
            ->where('is_voided', false)
            ->whereNull('student_acknowledged_at')
            ->whereHas('case', fn ($query) => $query
                ->where('status', 'pending_nte')
                ->whereDoesntHave('explanation'))
            ->lazyById();

        foreach ($notices as $notice) {
            if (! $notice->response_deadline || ! $notice->case) {
                continue;
            }

            if ($notice->response_deadline->lt($today)) {
                if (! $notice->overdue_flagged_at) {
                    $notice->update(['overdue_flagged_at' => now()]);

                    $notice->case->assignedOfficer?->notify(
                        new NteOverdueNotification($notice->case, $notice)
                    );

                    if ($notice->case->student) {
                        $notice->case->student->notify(
                            new NteOverdueStudentNotification($notice->case, $notice)
                        );
                    }

                    $overdue++;
                }

                continue;
            }

            if ($notice->deadline_reminder_sent_at
                || $notice->response_deadline->gt($today->copy()->addDays(2))) {
                continue;
            }

            if ($notice->case->student) {
                $notice->case->student->notify(
                    new NteDeadlineReminderNotification($notice->case, $notice)
                );
                $notice->update(['deadline_reminder_sent_at' => now()]);
                $reminders++;
            }
        }

        $this->info("Sent {$reminders} NTE reminder(s); flagged {$overdue} overdue NTE(s).");

        return self::SUCCESS;
    }
}
