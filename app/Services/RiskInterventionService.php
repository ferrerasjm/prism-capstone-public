<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RiskIntervention;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates and updates proactive risk interventions raised against
 * High/Critical students. Workflow documented in
 * docs/RISK_INTERVENTION_WORKFLOW.md.
 */
class RiskInterventionService
{
    public function create(User $student, array $data, int $actorId): RiskIntervention
    {
        return DB::transaction(function () use ($student, $data, $actorId): RiskIntervention {
            if ($student->role !== 'student') {
                throw new \RuntimeException('Risk interventions can only be recorded for student accounts.');
            }

            $score = RiskScore::query()
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $score || ! in_array($score->risk_band, ['high', 'critical'], true)) {
                throw new \RuntimeException(
                    'A new proactive support action requires a current high or critical risk assessment.'
                );
            }

            $this->assertActiveDisciplineOfficer($actorId);

            // Officers choose the order and the mix of support actions freely, but a
            // second open action of a type already running is a double submission or a
            // forgotten entry, not a second piece of work. The row lock above serialises
            // this per student, so two simultaneous submissions cannot both pass.
            $duplicate = RiskIntervention::query()
                ->where('student_id', $student->id)
                ->where('action_type', $data['action_type'])
                ->open()
                ->exists();

            if ($duplicate) {
                $label = RiskIntervention::ACTION_TYPES[$data['action_type']] ?? 'support action';

                throw new \RuntimeException(
                    "A {$label} is already open for this student. Close it before recording another."
                );
            }

            $snapshot = RiskScoreSnapshot::query()
                ->where('student_id', $student->id)
                ->orderByDesc('created_at')
                ->first();

            $intervention = RiskIntervention::create([
                'student_id' => $student->id,
                'risk_score_snapshot_id' => $snapshot?->id,
                'created_by' => $actorId,
                'assigned_to' => $actorId,
                'action_type' => $data['action_type'],
                'action_plan' => $data['action_plan'],
                'risk_score' => $score->composite_score,
                'risk_band' => $score->risk_band,
                'methodology_version' => config('risk_scoring.methodology_version'),
                'status' => 'in_progress',
                'follow_up_on' => $data['follow_up_on'],
                'started_at' => now(),
            ]);

            $this->audit($intervention, $actorId, 'create', null, [
                'student_id' => $student->id,
                'assigned_to' => $actorId,
                'action_type' => $data['action_type'],
                'status' => 'in_progress',
                'follow_up_on' => $data['follow_up_on'],
                'risk_score' => $score->composite_score,
                'risk_band' => $score->risk_band,
            ]);

            return $intervention;
        });
    }

    public function update(RiskIntervention $intervention, array $data, int $actorId): RiskIntervention
    {
        return DB::transaction(function () use ($intervention, $data, $actorId): RiskIntervention {
            $locked = RiskIntervention::query()->lockForUpdate()->findOrFail($intervention->id);

            if ($locked->isClosed()) {
                throw new \RuntimeException('Completed or cancelled support actions cannot be changed.');
            }

            $this->assertActiveDisciplineOfficer($actorId);

            $status = $data['status'];
            $this->assertStatusTransition($locked->status, $status);

            $oldValues = $locked->only([
                'status',
                'follow_up_on',
                'outcome',
                'started_at',
                'closed_at',
                'closed_by',
            ]);

            $now = now();
            $isClosing = in_array($status, ['completed', 'cancelled'], true);
            $locked->update([
                'status' => $status,
                'follow_up_on' => $data['follow_up_on'],
                'outcome' => $data['outcome'] ?? null,
                'started_at' => $status === 'in_progress' && ! $locked->started_at
                    ? $now
                    : $locked->started_at,
                'closed_at' => $isClosing ? $now : null,
                'closed_by' => $isClosing ? $actorId : null,
            ]);

            $newValues = $locked->fresh()->only(array_keys($oldValues));
            $this->audit($locked, $actorId, 'update', $oldValues, $newValues);

            return $locked->fresh();
        });
    }

    private function assertActiveDisciplineOfficer(int $userId): void
    {
        $exists = User::query()
            ->whereKey($userId)
            ->where('role', 'discipline_officer')
            ->where('status', 'active')
            ->exists();

        if (! $exists) {
            throw new \RuntimeException('Support actions can only be recorded by an active Discipline Officer.');
        }
    }

    private static function label(string $status): string
    {
        return RiskIntervention::STATUSES[$status] ?? ucfirst($status);
    }

    private function assertStatusTransition(string $current, string $next): void
    {
        // An action is open from the moment it is recorded; the only moves left are
        // rescheduling it or closing it.
        $allowed = [
            'in_progress' => ['in_progress', 'completed', 'cancelled'],
        ];

        if (! in_array($next, $allowed[$current] ?? [], true)) {
            $from = self::label($current);
            $to = self::label($next);

            throw new \RuntimeException("A support action cannot move from {$from} to {$to}.");
        }
    }

    private function audit(
        RiskIntervention $intervention,
        int $actorId,
        string $action,
        ?array $oldValues,
        array $newValues
    ): void {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'module' => 'risk_interventions',
            'record_id' => $intervention->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
