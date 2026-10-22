<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\UsAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class UsSupervisorAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(
        private readonly UsAssignment $assignment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $studentName = $this->assignment->student?->full_name ?? 'a student';
        $hours = $this->assignment->total_hours_required;
        $due = optional($this->assignment->target_completion_date)->format('M d, Y') ?? 'TBD';

        return [
            'kind' => 'us_supervisor_assignment',
            'title' => 'New University Service assignment',
            'message' => "{$studentName} has been assigned {$hours} hr of University Service under your supervision at {$this->assignment->office_name}. Target: {$due}.",
            'assignment_id' => $this->assignment->id,
            'student_id' => $this->assignment->student_id,
            'office_name' => $this->assignment->office_name,
            'total_hours_required' => $this->assignment->total_hours_required,
            'target_completion_date' => optional($this->assignment->target_completion_date)?->toDateString(),
            'action_label' => 'View Assignment',
            'url' => route('supervisor.us.show', $this->assignment, false),
        ];
    }
}
