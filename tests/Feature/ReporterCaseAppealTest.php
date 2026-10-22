<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\UpccReferral;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReporterCaseAppealTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reporting_user_sees_appeal_window_on_related_case(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));
        [$reportingUser, $report, $case] = $this->caseWithOpenAppealWindow();

        $response = $this->actingAs($reportingUser)->get(route('reporter.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Related Case');
        $response->assertSee($case->case_number);
        $response->assertSee('Decision Summary');
        $response->assertSee('UPCC decision has been distributed.');
        $response->assertSee('Appeal Deadline');
        $response->assertSee('Submit Appeal');
    }

    public function test_reporting_user_sees_the_student_they_reported(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));
        [$reportingUser, $report] = $this->caseWithOpenAppealWindow();

        $response = $this->actingAs($reportingUser)->get(route('reporter.reports.show', $report));

        $response->assertOk();
        $response->assertSee('Student Reported');
        $response->assertSee($report->student->list_name);
    }

    public function test_reporting_user_can_submit_appeal_during_open_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));
        Storage::fake('local');
        [$reportingUser, $report, $case] = $this->caseWithOpenAppealWindow();

        $response = $this->actingAs($reportingUser)->post(
            route('reporter.cases.appeal.store', $case),
            [
                'appeal_notes' => 'We respectfully request reconsideration because the decision does not reflect the severity of the incident reported.',
                'attachments' => [
                    UploadedFile::fake()->create('appeal-proof.pdf', 128, 'application/pdf'),
                ],
            ]
        );

        $response->assertRedirect(route('reporter.reports.show', $report));

        $referral = $case->upccReferral()->firstOrFail();
        $this->assertTrue($referral->appeal_filed);
        $this->assertSame('reconsideration_filed', $referral->status);
        $this->assertSame($reportingUser->id, $referral->appeal_filed_by);
        $this->assertSame('reporting_user_portal', $referral->appeal_source);

        $attachment = $referral->appealAttachments()->firstOrFail();
        $this->assertSame('appeal-proof.pdf', $attachment->file_name);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_reporting_user_cannot_appeal_a_case_from_another_users_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));
        [, , $case] = $this->caseWithOpenAppealWindow();
        $otherReportingUser = $this->user('reporting_user');

        $response = $this->actingAs($otherReportingUser)->post(
            route('reporter.cases.appeal.store', $case),
            ['appeal_notes' => 'This appeal should not be allowed for a report I do not own.']
        );

        $response->assertForbidden();
    }

    private function caseWithOpenAppealWindow(): array
    {
        $reportingUser = $this->user('reporting_user');
        $student = $this->user('student');
        $coordinator = $this->user('discipline_coordinator');

        $report = IncidentReport::create([
            'report_number' => 'RPT-'.fake()->unique()->numerify('#####'),
            'source_type' => 'faculty_report',
            'reported_by_user_id' => $reportingUser->id,
            'reported_by_name' => $reportingUser->full_name,
            'student_id' => $student->id,
            'date_of_incident' => now()->subDays(10)->toDateString(),
            'description' => 'A faculty member reported an incident involving the student for review.',
            'status' => 'converted_to_case',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'REPORTER-UPCC-'.fake()->unique()->numerify('####'),
            'case_type' => 'major',
            'incident_report_id' => $report->id,
            'student_id' => $student->id,
            'assigned_to' => $coordinator->id,
            'status' => 'pending_upcc',
            'case_type_locked' => true,
            'prior_offense_count' => 0,
            'forwarded_to_coordinator' => true,
            'forwarded_at' => now()->subDays(8),
        ]);

        UpccReferral::create([
            'case_id' => $case->id,
            'referred_by' => $coordinator->id,
            'referred_at' => now()->subDays(7),
            'scheduled_conference_at' => now()->subDays(2),
            'conference_location' => 'SDO Conference Room',
            'status' => 'concluded',
            'proceedings_type' => 'full_hearing',
            'decision_summary' => 'UPCC decision has been distributed.',
            'decision_issued_at' => now()->subDay(),
            'presidents_council_action' => 'approved',
            'presidents_council_acted_at' => now(),
            'decision_distributed_at' => now(),
            'appeal_window_start' => today()->toDateString(),
            'appeal_window_end' => today()->addDays(5)->toDateString(),
            'appeal_filed' => false,
        ]);

        return [$reportingUser, $report, $case];
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'ReporterAppeal',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
