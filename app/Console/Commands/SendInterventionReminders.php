<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DisciplineEducationAttendance;
use App\Models\Sanction;
use App\Notifications\DisciplineEducationAttendanceNotification;
use App\Notifications\DisciplineEducationScheduleNotification;
use App\Notifications\EvaluationMonitoringNotification;
use Illuminate\Console\Command;

class SendInterventionReminders extends Command
{
    protected $signature = 'sanctions:send-intervention-reminders';

    protected $description = 'Send upcoming Discipline Education and Evaluation Monitoring reminders';

    public function handle(): int
    {
        $disciplineEducationCount = 0;
        $evaluationCount = 0;

        DisciplineEducationAttendance::query()
            ->with(['sanction.student', 'sanction.resolution.case'])
            ->where('status', 'scheduled')
            ->where('session_at', '<', today())
            ->each(function (DisciplineEducationAttendance $attendance): void {
                $sanction = $attendance->sanction;
                if (! $sanction || in_array($sanction->status, ['completed', 'waived'], true)) {
                    return;
                }

                $attendance->update(['status' => 'absent']);
                $sanction->update([
                    'status' => 'non_compliant',
                    'completed_at' => null,
                    'verified_by' => null,
                ]);
                $sanction->student?->notify(
                    new DisciplineEducationAttendanceNotification($sanction, $attendance)
                );
            });

        DisciplineEducationAttendance::query()
            ->where('status', 'scheduled')
            ->where('session_at', '<=', now())
            ->whereHas('sanction', fn ($query) => $query->where('status', 'pending'))
            ->with('sanction')
            ->each(fn (DisciplineEducationAttendance $attendance) => $attendance->sanction?->update(['status' => 'in_progress']));

        DisciplineEducationAttendance::query()
            ->with(['sanction.student', 'sanction.resolution.case'])
            ->where('status', 'scheduled')
            ->whereNull('reminder_sent_at')
            ->whereBetween('session_at', [now(), now()->addDays(2)])
            ->whereHas('sanction', fn ($query) => $query->whereIn('status', ['pending', 'in_progress']))
            ->each(function (DisciplineEducationAttendance $attendance) use (&$disciplineEducationCount): void {
                $student = $attendance->sanction?->student;
                if (! $student) {
                    return;
                }

                $student->notify(
                    new DisciplineEducationScheduleNotification($attendance->sanction, $attendance, true)
                );
                $attendance->update(['reminder_sent_at' => now()]);
                $disciplineEducationCount++;
            });

        Sanction::query()
            ->with(['student', 'resolution.case'])
            ->where('sanction_type', 'evaluation_monitoring')
            ->where('status', 'in_progress')
            ->whereNull('intervention_reminder_sent_at')
            ->whereBetween('due_date', [today(), today()->addDays(3)])
            ->each(function (Sanction $sanction) use (&$evaluationCount): void {
                if (! $sanction->student) {
                    return;
                }

                $sanction->student->notify(
                    new EvaluationMonitoringNotification($sanction, EvaluationMonitoringNotification::REMINDER)
                );
                $sanction->update(['intervention_reminder_sent_at' => now()]);
                $evaluationCount++;
            });

        $this->info("Sent {$disciplineEducationCount} Discipline Education reminder(s) and {$evaluationCount} Evaluation Monitoring reminder(s).");

        return self::SUCCESS;
    }
}
