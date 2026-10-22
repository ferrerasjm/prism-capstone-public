<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DisciplineEducationAttendance;
use App\Models\Sanction;

class DisciplineEducationAttendanceNotification extends StudentNotification
{
    public function __construct(
        private readonly Sanction $sanction,
        private readonly DisciplineEducationAttendance $attendance,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        $present = $this->attendance->status === 'present';
        $case = $this->sanction->resolution?->case;

        return [
            'kind' => $present ? 'discipline_education_completed' : 'discipline_education_absent',
            'title' => $present ? 'Discipline Education completed' : 'Discipline Education attendance missed',
            'message' => $present
                ? 'Your Discipline Education Program attendance has been recorded as present.'
                : 'Your Discipline Education Program attendance has been recorded as absent and the sanction is now non-compliant.',
            'sanction_id' => $this->sanction->id,
            'attendance_id' => $this->attendance->id,
            'case_id' => $case?->id,
            'case_number' => $case?->case_number,
            'action_label' => 'View My Sanctions',
            'url' => route('student.sanctions.index', [], false),
        ];
    }
}
