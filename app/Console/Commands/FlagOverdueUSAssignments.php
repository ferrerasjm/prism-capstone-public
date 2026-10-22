<?php

namespace App\Console\Commands;

use App\Services\USAssignmentService;
use Illuminate\Console\Command;

/**
 * Flags University Service assignments past their target completion date
 * for officer review without automatically applying registration holds. Scheduled daily.
 */
class FlagOverdueUSAssignments extends Command
{
    protected $signature = 'us:flag-overdue';

    protected $description = 'Notify officers to review US assignments past their target completion date';

    public function handle(USAssignmentService $service): int
    {
        $sent = $service->sendStudentDeadlineNotifications();
        $this->info("Sent {$sent} student US deadline notification(s).");
        $flagged = $service->flagOverdueAssignments();
        $this->info("Flagged {$flagged} overdue US assignment(s).");

        return 0;
    }
}
