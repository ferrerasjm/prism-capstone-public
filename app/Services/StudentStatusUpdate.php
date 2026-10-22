<?php

namespace App\Services;

use App\Models\User;

class StudentStatusUpdate
{
    public function snapshot(User $user): array
    {
        return array_intersect_key($user->getRawOriginal(), array_flip(['role', 'status', 'student_status', 'student_status_on', 'deactivation_category',
            'deactivation_effective_on', 'status_before_deactivation', 'updated_at']))
            + ['unfinished_setup' => $user->hasUnfinishedSetup()];
    }

    public function changes(User $user, string $status, string $date): array
    {
        $changes = ['student_status' => $status, 'student_status_on' => $date];
        $schoolDeparture = in_array($user->deactivation_category, ['graduated', 'transferred', 'withdrawn', 'not_enrolled'], true);
        // An inactive account with a separate access restriction stays restricted.
        if ($user->status === 'inactive' && ! $schoolDeparture) {
            return $changes;
        }
        if ($status === 'enrolled') {
            $changes += [
                'status' => ($user->hasUnfinishedSetup() || $user->status === 'pending_setup' || $user->status_before_deactivation === 'pending_setup') ? 'pending_setup' : 'active',
                'deactivation_category' => null, 'deactivation_effective_on' => null, 'status_before_deactivation' => null,
            ];
        } else {
            $changes += [
                'status' => 'inactive', 'deactivation_category' => $status, 'deactivation_effective_on' => $date,
                'status_before_deactivation' => $user->status === 'inactive' ? $user->status_before_deactivation
                    : ($user->hasUnfinishedSetup() ? 'pending_setup' : $user->status),
            ];
        }

        return $changes;
    }
}
