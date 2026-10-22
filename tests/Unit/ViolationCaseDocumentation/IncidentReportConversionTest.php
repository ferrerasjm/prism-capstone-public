<?php

declare(strict_types=1);

namespace Tests\Unit\ViolationCaseDocumentation;

use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\IncidentReportSubject;
use App\Models\RiskFactorWeight;
use App\Models\User;
use App\Services\IncidentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Module 2 - Violation and Case Documentation
 * Component: Incident Report to Case Conversion
 *            (App\Services\IncidentReportService::convertToCase)
 * Test Cycle No. 1 - 5 test cases.
 */
class IncidentReportConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seedRiskWeights();
    }

    /** Scenario 1 - Normal: converting a named report opens one case for the subject. */
    public function test_converting_a_named_report_opens_one_case_for_the_subject(): void
    {
        [$report, $officer, $students] = $this->report(1);

        $cases = (new IncidentReportService)->convertToCase($report, $officer);

        $this->assertCount(1, $cases);
        $this->assertSame($students[0]->id, $cases[0]->student_id);
        $this->assertSame('open', $cases[0]->status);
        $this->assertSame($officer->id, $cases[0]->assigned_to);
        $this->assertSame('converted_to_case', $report->fresh()->status);
    }

    /** Scenario 2 - Boundary: classification is deferred, so the new case has no type yet. */
    public function test_a_converted_case_carries_no_case_type_until_an_offense_is_confirmed(): void
    {
        [$report, $officer] = $this->report(1);

        $cases = (new IncidentReportService)->convertToCase($report, $officer);

        $this->assertNull($cases[0]->case_type);
        $this->assertFalse($cases[0]->case_type_locked);
        $this->assertFalse($cases[0]->escalation_flagged);
    }

    /** Scenario 3 - Normal: a report naming several subjects opens one case each. */
    public function test_a_report_naming_several_subjects_opens_one_case_each(): void
    {
        [$report, $officer, $students] = $this->report(3);

        $cases = (new IncidentReportService)->convertToCase($report, $officer);

        $this->assertCount(3, $cases);
        $this->assertEqualsCanonicalizing(
            collect($students)->pluck('id')->all(),
            collect($cases)->pluck('student_id')->all()
        );
        $this->assertCount(3, collect($cases)->pluck('case_number')->unique());
    }

    /** Scenario 4 - Failure: a report with no named subject cannot be converted. */
    public function test_a_report_with_no_named_subject_cannot_be_converted(): void
    {
        [$report, $officer] = $this->report(0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot convert: no named subject(s) on this report.');

        (new IncidentReportService)->convertToCase($report, $officer);
    }

    /** Scenario 5 - Failure: an already-converted report cannot be converted twice. */
    public function test_an_already_converted_report_cannot_be_converted_twice(): void
    {
        [$report, $officer] = $this->report(1);
        (new IncidentReportService)->convertToCase($report, $officer);

        $countAfterFirst = DisciplinaryCase::count();

        try {
            (new IncidentReportService)->convertToCase($report->fresh(), $officer);
            $this->fail('A converted report must not be convertible again.');
        } catch (RuntimeException $exception) {
            $this->assertSame('This report can no longer be converted to a case.', $exception->getMessage());
        }

        $this->assertSame($countAfterFirst, DisciplinaryCase::count());
    }

    /** @return array{0: IncidentReport, 1: User, 2: array<int, User>} */
    private function report(int $subjectCount): array
    {
        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Reviewing',
            'email' => 'convert.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $reporter = User::create([
            'last_name' => 'Bautista',
            'first_name' => 'Reporting',
            'email' => 'convert.reporter@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'reporting_user',
            'status' => 'active',
            'office_name' => 'Security Office',
        ]);

        $report = IncidentReport::create([
            'report_number' => 'IR-2026-7001',
            'reported_by_user_id' => $reporter->id,
            'source_type' => 'security_report',
            'date_of_incident' => '2026-07-30',
            'time_of_incident' => '14:30',
            'location' => 'Main corridor, 3rd floor',
            'description' => 'Two students were seen defacing the corridor wall after class hours.',
            'violation_type' => 'Vandalism',
            'status' => 'pending_review',
        ]);

        $students = [];
        for ($index = 1; $index <= $subjectCount; $index++) {
            $student = User::create([
                'last_name' => 'Santos',
                'first_name' => 'Subject'.$index,
                'email' => "convert.subject{$index}@students.nu-clark.edu.ph",
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
            ]);

            IncidentReportSubject::create([
                'incident_report_id' => $report->id,
                'student_id' => $student->id,
                'created_at' => now(),
            ]);

            $students[] = $student;
        }

        return [$report->fresh(), $officer, $students];
    }

    private function seedRiskWeights(): void
    {
        RiskFactorWeight::query()->delete();

        foreach (config('risk_scoring.factors') as $code => $factor) {
            RiskFactorWeight::create([
                'factor_code' => $code,
                'factor_name' => $factor['name'],
                'factor_type' => $factor['type'],
                'count_cap' => $factor['cap'],
                'weight' => $factor['weight'],
                'description' => $factor['description'],
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }
    }
}
