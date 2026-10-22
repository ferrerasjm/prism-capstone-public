<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DisciplinaryCase;
use App\Models\IncidentAttachment;
use App\Models\IncidentReport;
use App\Models\IncidentReportSubject;
use App\Models\Sanction;
use App\Models\StudentMonitoringFlag;
use App\Models\User;
use App\Notifications\CaseCreatedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles incident report intake: reference-number generation, private
 * evidence attachment storage, and conversion of a report into one
 * disciplinary case per named subject.
 */
class IncidentReportService
{
    public function generateReportNumber(): string
    {
        return $this->allocateYearlyNumber(
            sequenceKeyPrefix: 'incident_reports',
            numberPrefix: 'IR',
            table: 'incident_reports',
            column: 'report_number',
        );
    }

    public function storeAttachment(
        UploadedFile $file,
        IncidentReport $report,
        int $uploadedById
    ): IncidentAttachment {
        $extension = strtolower($file->getClientOriginalExtension());
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'attachment';
        $displayName = "{$baseName}.{$extension}";
        $storedName = "{$baseName}-".Str::uuid().".{$extension}";
        $storagePath = "incident-attachments/{$report->id}/{$storedName}";
        $security = app(AttachmentSecurityService::class)->inspect($file);

        Storage::disk('local')->putFileAs("incident-attachments/{$report->id}", $file, $storedName);

        return IncidentAttachment::create([
            'incident_report_id' => $report->id,
            'file_name' => $displayName,
            'file_path' => $storagePath,
            'file_type' => $extension,
            'file_size_bytes' => $file->getSize(),
            'file_hash' => $security['file_hash'],
            'scan_status' => $security['scan_status'],
            'scanned_at' => $security['scanned_at'],
            'uploaded_by' => $uploadedById,
            'created_at' => now(),
        ]);
    }

    /**
     * Converts an incident report into one disciplinary case per named
     * subject.
     *
     * Re-reads the report under lockForUpdate inside the transaction so
     * two officers converting the same report concurrently cannot each
     * create a duplicate set of cases - the second attempt sees the
     * already-converted status and is refused.
     */
    public function convertToCase(IncidentReport $report, User $officer): array
    {
        $cases = [];

        DB::transaction(function () use ($report, $officer, &$cases) {
            $report = IncidentReport::query()->lockForUpdate()->findOrFail($report->id);

            if (in_array($report->status, ['converted_to_case', 'dismissed'], true)) {
                throw new \RuntimeException('This report can no longer be converted to a case.');
            }

            $subjects = $this->resolveSubjects($report);
            if (empty($subjects)) {
                throw new \RuntimeException('Cannot convert: no named subject(s) on this report.');
            }

            $barredNames = collect($subjects)
                ->filter(fn (User $student) => $this->isBarredFromNewCases($student))
                ->pluck('full_name');

            if ($barredNames->isNotEmpty()) {
                throw new \RuntimeException(
                    'Cannot convert: '.$barredNames->join(', ').
                    ' already has an Exclusion, Expulsion, or an active Non-Readmission decision in'.
                    ' effect and cannot be the subject of a new case. Remove them from this report to'.
                    ' proceed with the remaining subject(s), if any.'
                );
            }

            $oldReportStatus = $report->status;

            foreach ($subjects as $student) {
                $caseNumber = $this->generateCaseNumber();

                $priorCount = DisciplinaryCase::where('student_id', $student->id)
                    ->where('status', 'resolved')
                    ->count();

                $case = DisciplinaryCase::create([
                    'case_number' => $caseNumber,
                    'incident_report_id' => $report->id,
                    'student_id' => $student->id,
                    // Classification is a separate DO decision. A converted
                    // report must not presume severity before an offense is confirmed.
                    'case_type' => null,
                    'case_type_locked' => false,
                    'assigned_to' => $officer->id,
                    'status' => 'open',
                    'prior_offense_count' => $priorCount,
                    'escalation_flagged' => false,
                    'forwarded_to_coordinator' => false,
                    'void_requires_coordinator_approval' => false,
                ]);

                $cases[] = $case;

                $student->notify(new CaseCreatedNotification($case));

                AuditLog::create([
                    'user_id' => $officer->id,
                    'action' => 'create',
                    'module' => 'cases',
                    'record_id' => $case->id,
                    'new_values' => [
                        'case_number' => $caseNumber,
                        'from_report' => $report->report_number,
                        'student' => $student->full_name,
                        'prior_offense_count' => $priorCount,
                    ],
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);
            }

            $report->update([
                'status' => 'converted_to_case',
                'reviewed_by' => $officer->id,
                'reviewed_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $officer->id,
                'action' => 'update',
                'module' => 'incident_reports',
                'record_id' => $report->id,
                'old_values' => ['status' => $oldReportStatus],
                'new_values' => ['status' => 'converted_to_case', 'cases_created' => count($cases)],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });

        collect($cases)
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->each(fn (int $studentId) => app(RiskScoringService::class)
                ->computeForStudent($studentId, 'case_created', $officer->id));

        return $cases;
    }

    /**
     * Exclusion and Expulsion are permanent — the student is no longer enrolled, so
     * there is nothing left to case against them for. Non-Readmission is term-bound:
     * it blocks new cases only while its registration hold is still active, since
     * that hold is exactly the system's record of "still serving this decision."
     * A hold raised for an unrelated reason (e.g. University Service non-compliance)
     * blocks the same way — a student who currently cannot enroll shouldn't be the
     * subject of a brand new case either.
     */
    private function isBarredFromNewCases(User $student): bool
    {
        $hasPermanentDecision = Sanction::where('student_id', $student->id)
            ->whereIn('sanction_type', ['exclusion', 'expulsion'])
            ->exists();

        if ($hasPermanentDecision) {
            return true;
        }

        return StudentMonitoringFlag::where('student_id', $student->id)
            ->where('flag_type', 'registration_hold')
            ->where('is_active', true)
            ->exists();
    }

    public function generateCaseNumber(): string
    {
        return $this->allocateYearlyNumber(
            sequenceKeyPrefix: 'disciplinary_cases',
            numberPrefix: 'DC',
            table: 'cases',
            column: 'case_number',
        );
    }

    private function allocateYearlyNumber(
        string $sequenceKeyPrefix,
        string $numberPrefix,
        string $table,
        string $column,
    ): string {
        $year = now()->year;
        $sequenceKey = "{$sequenceKeyPrefix}:{$year}";
        $seedValue = $this->maxExistingYearlySequence($table, $column, $numberPrefix, $year);

        $nextValue = DB::transaction(function () use ($sequenceKey, $numberPrefix, $year, $seedValue): int {
            $now = now();

            DB::table('number_sequences')->insertOrIgnore([
                'sequence_key' => $sequenceKey,
                'prefix' => $numberPrefix,
                'year' => $year,
                'current_value' => $seedValue,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = DB::table('number_sequences')
                ->where('sequence_key', $sequenceKey)
                ->lockForUpdate()
                ->first();

            $currentValue = max((int) $sequence->current_value, $seedValue);
            $nextValue = $currentValue + 1;

            DB::table('number_sequences')
                ->where('sequence_key', $sequenceKey)
                ->update([
                    'current_value' => $nextValue,
                    'updated_at' => $now,
                ]);

            return $nextValue;
        });

        return sprintf('%s-%d-%04d', $numberPrefix, $year, $nextValue);
    }

    private function maxExistingYearlySequence(string $table, string $column, string $prefix, int $year): int
    {
        $pattern = "/^{$prefix}-{$year}-(\\d+)$/";

        return DB::table($table)
            ->where($column, 'like', "{$prefix}-{$year}-%")
            ->pluck($column)
            ->map(fn (string $number): int => preg_match($pattern, $number, $matches) === 1
                ? (int) $matches[1]
                : 0)
            ->max() ?? 0;
    }

    private function resolveSubjects(IncidentReport $report): array
    {
        $subjects = IncidentReportSubject::where('incident_report_id', $report->id)
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();

        if ($report->student_id) {
            $student = User::find($report->student_id);
            if ($student) {
                $subjects->prepend($student);
            }
        }

        return $subjects
            ->unique('id')
            ->values()
            ->all();
    }
}
