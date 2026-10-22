<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DisciplineEducationAttendance;
use App\Models\Sanction;

class DisciplineEducationScheduleNotification extends StudentNotification
{
    public function __construct(
        private readonly Sanction $sanction,
        private readonly DisciplineEducationAttendance $attendance,
        private readonly bool $reminder = false,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        $case = $this->sanction->resolution?->case;
        $date = $this->attendance->session_at->format('F d, Y \a\t g:i A');

        return [
            'kind' => $this->reminder ? 'discipline_education_reminder' : 'discipline_education_scheduled',
            'title' => $this->reminder ? 'Discipline Education session reminder' : 'Discipline Education session scheduled',
            'message' => ($this->reminder ? 'Reminder: ' : '')."Your Discipline Education Program session is scheduled for {$date}.",
            'sanction_id' => $this->sanction->id,
            'attendance_id' => $this->attendance->id,
            'case_id' => $case?->id,
            'case_number' => $case?->case_number,
            'deadline' => $this->attendance->session_at->toDateString(),
            'action_label' => 'View My Sanctions',
            'url' => route('student.sanctions.index', [], false),
        ];
    }
}
