<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IncidentReport;
use App\Models\OffenseCatalog;
use App\Models\RiskScore;
use App\Models\Sanction;
use App\Models\SanctionMatrix;
use App\Models\User;
use App\Services\CaseService;
use App\Services\IncidentReportService;
use App\Services\SanctionRecommendationService;
use Database\Seeders\RiskFactorWeightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DisciplinaryCaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_major_category_two_case_lifecycle_from_report_to_nfi(): void
    {
        Carbon::setTestNow('2026-07-16 09:00:00');
        $this->seed(RiskFactorWeightSeeder::class);

        $officer = $this->user('discipline_officer', 'lifecycle-officer@example.test');
        $student = $this->user('student', 'lifecycle-student@example.test');
        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-LIFECYCLE',
            'offense_type' => 'major',
            'title' => 'Lifecycle Major Offense',
            'description' => 'Major offense used for end-to-end lifecycle coverage.',
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '42',
            'keywords' => ['lifecycle'],
            'created_at' => now(),
        ]);

        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id,
            'offense_count' => 1,
            'sanction_category' => 2,
            'notes' => 'Category 2 lifecycle sanction.',
        ]);

        $report = IncidentReport::create([
            'report_number' => 'IR-LIFECYCLE-001',
            'source_type' => 'direct_encoding',
            'reported_by_user_id' => $officer->id,
            'student_id' => $student->id,
            'date_of_incident' => today(),
            'description' => 'Lifecycle report that should become a full disciplinary case.',
            'status' => 'under_review',
        ]);

        $incidentService = new IncidentReportService;
        $caseService = new CaseService;
        $case = $incidentService->convertToCase($report, $officer)[0];

        $this->assertSame('converted_to_case', $report->fresh()->status);
        $this->assertSame('open', $case->status);
        $this->assertStringStartsWith('DC-2026-', $case->case_number);

        $caseService->confirmOffense($case->fresh(), $offense->id, $officer->id);
        $caseService->confirmSanction($case->fresh(), 2, $officer->id);
        $notice = $caseService->issueNTE($case->fresh(), [
            'incident_date' => today()->toDateString(),
            'additional_content' => 'Explain the incident in writing.',
        ], $officer->id);

        $case = $case->fresh(['notices']);
        $this->assertSame('pending_nte', $case->status);
        $this->assertSame('major', $case->case_type);
        $this->assertTrue($case->case_type_locked);
        $this->assertSame('notice_to_explain', $notice->notice_type);
        $this->assertSame('Section VI.E', $notice->handbook_section);

        $this->actingAs($student)
            ->post(route('student.cases.explanation.store', $case), [
                'explanation_text' => 'I acknowledge the notice and respectfully explain the incident with enough detail for review.',
            ])
            ->assertRedirect(route('student.cases.show', $case));

        $case = $case->fresh(['explanation']);
        $this->assertSame('awaiting_explanation', $case->status);
        $this->assertNotNull($case->explanation);
        $this->assertNotNull($notice->fresh()->student_acknowledged_at);

        $caseService->saveEvaluation(
            $case,
            $case->explanation,
            'full_admission',
            'Student accepted responsibility and is eligible for direct SDO resolution.',
            $officer->id,
            new SanctionRecommendationService
        );

        $case = $case->fresh(['explanation']);
        $this->assertSame('under_review', $case->status);
        $this->assertSame('direct_resolution', $case->path_recommendation);
        $this->assertSame('full_admission', $case->explanation->admission_type);

        $caseService->initiateResolution($case->fresh(), $officer->id);

        $resolution = $caseService->recordResolution($case->fresh(), [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 8,
            'counseling_referred' => true,
            'discipline_education_required' => true,
            'discipline_education_session_at' => now()->addWeek(),
            'evaluation_monitoring_required' => true,
            'evaluation_monitoring_end_date' => today()->addMonth()->toDateString(),
            'notes' => 'Category 2 formative intervention for lifecycle coverage.',
        ], $officer->id);

        $nfi = $caseService->issueNFI($case->fresh(['resolution']), $resolution, $officer->id);

        $case = $case->fresh(['resolution.sanctions']);
        $this->assertSame('resolved', $case->status);
        $this->assertSame('notice_of_formative_intervention', $nfi->notice_type);
        $this->assertTrue(RiskScore::where('student_id', $student->id)->exists());
        $this->assertSame(4, $case->resolution->sanctions->count());

        $statusesByType = Sanction::where('case_resolution_id', $resolution->id)
            ->pluck('status', 'sanction_type');

        $this->assertSame('pending', $statusesByType['university_service']);
        $this->assertSame('in_progress', $statusesByType['counseling_referral']);
        $this->assertSame('pending', $statusesByType['discipline_education']);
        $this->assertSame('in_progress', $statusesByType['evaluation_monitoring']);

        $studentNotificationKinds = $student->notifications()
            ->get()
            ->pluck('data')
            ->pluck('kind')
            ->all();

        $this->assertContains('case_created', $studentNotificationKinds);
        $this->assertContains('nte', $studentNotificationKinds);
        $this->assertContains('case_resolved', $studentNotificationKinds);
        $this->assertContains('sanctions_assigned', $studentNotificationKinds);
        $this->assertContains('nfi', $studentNotificationKinds);

        $officerNotificationKinds = $officer->notifications()
            ->get()
            ->pluck('data')
            ->pluck('kind')
            ->all();

        $this->assertContains('case_explanation_submitted', $officerNotificationKinds);
        $this->assertDatabaseHas('case_notices', ['case_id' => $case->id, 'notice_type' => 'notice_to_explain']);
        $this->assertDatabaseHas('case_notices', ['case_id' => $case->id, 'notice_type' => 'notice_of_formative_intervention']);
        $this->assertGreaterThanOrEqual(5, AuditLog::where('record_id', $case->id)->where('module', 'cases')->count());

        $this->actingAs($student)
            ->get(route('student.cases.show', $case))
            ->assertOk()
            ->assertSee($case->case_number);

        Carbon::setTestNow();
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Lifecycle',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
