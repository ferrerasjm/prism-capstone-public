<?php

namespace App\Notifications;

class RegistrationHoldNotification extends StudentNotification
{
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'registration_hold_applied',
            'title' => 'Registration hold applied',
            'message' => 'A registration hold has been recorded for you. This affects your eligibility to enroll. Please contact the Student Discipline Office about the requirements to clear it.',
            'action_label' => 'View Sanctions',
            'url' => route('student.sanctions.index', [], false),
        ];
    }
}
