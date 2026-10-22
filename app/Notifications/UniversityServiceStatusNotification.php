<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\UsAssignment;

class UniversityServiceStatusNotification extends StudentNotification
{
    public function __construct(
        private readonly UsAssignment $assignment,
        private readonly string $event,
        private readonly ?string $detail = null,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->event === 'completed'
            ? ['database']
            : parent::via($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        [$title, $message] = match ($this->event) {
            'assigned' => [
                'University Service assignment created',
                "You are assigned {$this->assignment->requiredDuration()} of University Service at {$this->assignment->office_name}. Target completion: {$this->assignment->target_completion_date->format('F d, Y')}.",
            ],
            'completed' => [
                'University Service completed',
                'Your required University Service hours have been completed and recorded.',
            ],
            'non_compliant' => [
                'University Service requires immediate follow-up',
                'Your University Service assignment was marked non-compliant. Please coordinate with the Student Discipline Office.',
            ],
            'deadline_reminder' => [
                'University Service is due tomorrow',
                "Your University Service deadline is tomorrow, {$this->assignment->target_completion_date->format('F d, Y')}, with {$this->assignment->remainingDuration()} remaining. Please coordinate with your supervisor or the Student Discipline Office if you need more time.",
            ],
            'deadline_missed' => [
                'University Service deadline passed',
                "Your University Service deadline has passed with {$this->assignment->remainingDuration()} remaining. Please contact the Student Discipline Office to review the delay. Missing this deadline does not automatically place your registration on hold.",
            ],
            'extended' => [
                'University Service deadline extended',
                "Your target completion date has been moved to {$this->assignment->target_completion_date->format('F d, Y')}.",
            ],
            'session_rejected' => [
                'A University Service session was rejected',
                "Your supervisor rejected a logged session and it no longer counts toward your required hours."
                    .($this->detail ? " Reason: {$this->detail}" : ''),
            ],
            default => ['University Service updated', 'Your University Service assignment has been updated.'],
        };

        return [
            'kind' => 'university_service_'.$this->event,
            'title' => $title,
            'message' => $message,
            'assignment_id' => $this->assignment->id,
            'status' => $this->assignment->status,
            'office_name' => $this->assignment->office_name,
            'total_hours_required' => $this->assignment->total_hours_required,
            'target_completion_date' => $this->assignment->target_completion_date?->toDateString(),
            'action_label' => 'View University Service',
            'url' => $this->assignment->exists
                ? route('student.us.show', $this->assignment, false)
                : route('student.us.index', [], false),
        ];
    }
}
