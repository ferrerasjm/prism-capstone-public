<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\AuditLog;
use App\Models\CaseResolution;
use App\Models\CounselingReferral;
use App\Models\DisciplinaryCase;
use App\Models\DisciplineEducationAttendance;
use App\Models\OffenseCatalog;
use App\Models\RiskScoreSnapshot;
use App\Models\Sanction;
use App\Models\SanctionMatrix;
use App\Models\StudentMonitoringFlag;
use App\Models\StudentProfile;
use App\Models\UniversityServiceOffice;
use App\Models\UpccReferral;
use App\Models\UsAssignment;
use App\Models\User;
use App\Notifications\CaseResolvedNotification;
use App\Notifications\DisciplineEducationScheduleNotification;
use App\Notifications\GuidanceReferralStatusNotification;
use App\Notifications\NfiIssuedNotification;
use App\Notifications\SanctionsAssignedNotification;
use App\Notifications\UniversityServiceStatusNotification;
use App\Services\CaseService;
use App\Services\MajorOffenseWorkflow;
use App\Services\USAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\SignsMobileAttendance;
use Tests\TestCase;

/**
 * INTEGRATION TEST GROUP 3 — Sanction, Referral and University Service Compliance.
 *
 * Exercises the seam between the coordinator resolution screen, CaseService
 * sanction generation, the Notice of Formative Intervention, the JWT-authenticated
 * mobile supervisor API with its TOTP-backed QR sessions, USAssignmentService
 * compliance bookkeeping (registration holds), GuidanceReferralService, and the
 * RiskScoringService recomputations these obligations trigger.
 *
 * Run only this group:
 *   php artisan test tests/Feature/Integration/SanctionComplianceMonitoringIntegrationTest.php
 */
class SanctionComplianceMonitoringIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use SignsMobileAttendance;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * IT-03-01 — Coordinator resolution screen ↔ CaseService sanction generation
     * ↔ counseling referral + discipline education records ↔ student notifications.
     */
    public function test_recording_a_category_two_resolution_generates_the_full_obligation_set(): void
    {
        [$coordinator, $officer, $student, $case] = $this->upccCaseAwaitingResolution(category: 2, recommendedHours: 10.0);
        $sessionAt = now()->addDays(3)->format('Y-m-d\TH:i');

        $this->actingAs($coordinator)
            ->post(route('coordinator.cases.resolution.store', $case), [
                'intervention_category' => 2,
                'us_hours_assigned' => 10,
                'counseling_referred' => '1',
                'referral_reason' => 'Formative intervention includes a counseling component per the UPCC decision.',
                'urgency' => 'routine',
                'discipline_education_required' => '1',
                'discipline_education_session_at' => $sessionAt,
                'evaluation_monitoring_required' => '1',
                'evaluation_monitoring_end_date' => today()->addMonth()->toDateString(),
                'notes' => 'Formative interventions agreed during the UPCC proceedings.',
            ])
            ->assertRedirect(route('coordinator.cases.show', $case))
            ->assertSessionHas('success');

        $case->refresh();
        $resolution = CaseResolution::where('case_id', $case->id)->firstOrFail();

        $this->assertSame('resolved', $case->status);
        $this->assertSame('upcc', $resolution->resolution_level);
        $this->assertSame(2, $resolution->intervention_category);
        $this->assertSame(
            MajorOffenseWorkflow::appealDeadline(today())->toDateString(),
            $resolution->appeal_deadline->toDateString(),
            'The appeal deadline must come from the MajorOffenseWorkflow working-day rule.'
        );

        $sanctionTypes = Sanction::where('case_resolution_id', $resolution->id)
            ->orderBy('id')
            ->pluck('sanction_type')
            ->all();

        $this->assertSame([
            'university_service',
            'counseling_referral',
            'discipline_education',
            'evaluation_monitoring',
        ], $sanctionTypes);

        $counselingSanction = Sanction::where('case_resolution_id', $resolution->id)
            ->where('sanction_type', 'counseling_referral')
            ->firstOrFail();

        $this->assertDatabaseHas('counseling_referrals', [
            'sanction_id' => $counselingSanction->id,
            'student_id' => $student->id,
            'status' => 'pending',
            'guidance_officer_id' => null,
            'referred_by' => $coordinator->id,
        ]);

        $attendance = DisciplineEducationAttendance::firstOrFail();

        $this->assertSame('scheduled', $attendance->status);
        $this->assertNotNull($attendance->notification_sent_at);
        $this->assertSame($coordinator->id, $attendance->recorded_by);

        foreach ([
            CaseResolvedNotification::class,
            SanctionsAssignedNotification::class,
            DisciplineEducationScheduleNotification::class,
        ] as $notification) {
            $this->assertDatabaseHas('notifications', [
                'type' => $notification,
                'notifiable_id' => $student->id,
            ]);
        }

        $this->assertSame($officer->id, $case->assigned_to);
        $this->assertNotNull(RiskScoreSnapshot::where('student_id', $student->id)->first());
    }

    /**
     * IT-03-02 — NFI issuance ↔ sanction activation ↔ notice record ↔ per-sanction
     * audit trail.
     */
    public function test_issuing_the_nfi_activates_the_recorded_obligations_and_notifies_the_student(): void
    {
        [$coordinator, , $student, $case] = $this->upccCaseAwaitingResolution(category: 2, recommendedHours: 8.0);

        $resolution = app(CaseService::class)->recordResolution($case, [
            'resolution_level' => 'upcc',
            'upcc_referral_id' => $case->upccReferral?->id,
            'intervention_category' => 2,
            'us_hours_assigned' => 8,
            'counseling_referred' => true,
            'evaluation_monitoring_required' => true,
            'evaluation_monitoring_end_date' => today()->addMonth()->toDateString(),
        ], $coordinator->id);

        $this->actingAs($coordinator)
            ->from(route('coordinator.cases.show', $case))
            ->post(route('coordinator.cases.issue-nfi', $case))
            ->assertRedirect(route('coordinator.cases.show', $case))
            ->assertSessionHas('success');

        $notice = $case->notices()
            ->where('notice_type', 'notice_of_formative_intervention')
            ->firstOrFail();

        $this->assertSame(2, $notice->additional_content['intervention_category']);
        $this->assertFalse((bool) $notice->is_voided);

        $statuses = Sanction::where('case_resolution_id', $resolution->id)
            ->pluck('status', 'sanction_type')
            ->all();

        $this->assertSame('pending', $statuses['university_service']);
        $this->assertSame('in_progress', $statuses['counseling_referral']);
        $this->assertSame('in_progress', $statuses['evaluation_monitoring']);

        $this->assertDatabaseHas('notifications', [
            'type' => NfiIssuedNotification::class,
            'notifiable_id' => $student->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'case_notices',
            'record_id' => $notice->id,
            'action' => 'create',
            'user_id' => $coordinator->id,
        ]);
        $this->assertSame(
            3,
            AuditLog::where('module', 'sanctions')->count(),
            'Every obligation activated by the NFI must be individually audited.'
        );
    }

    /**
     * IT-03-03 — Mobile supervisor app (JWT API) ↔ TOTP QR verification ↔
     * USAssignmentService hour roll-up ↔ sanction completion ↔ registration hold.
     */
    public function test_mobile_qr_sessions_complete_the_service_obligation_and_lift_the_registration_hold(): void
    {
        UniversityServiceOffice::firstOrCreate(['name' => 'Library'], ['is_active' => true]);

        $supervisor = $this->user('us_supervisor', 'it03-supervisor@nu-clark.edu.ph', [
            'office_name' => 'Library',
            'institutional_id' => 'US-SUP-030',
        ]);
        $student = $this->student('it03-service@students.nu-clark.edu.ph', '2026-30001');
        $officer = $this->user('discipline_officer', 'it03-officer@nu-clark.edu.ph');

        [$resolution, $sanction] = $this->serviceObligation($student, $officer, 'IT0303', 4.0);

        $assignment = UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'assigned_by' => $officer->id,
            'office_name' => 'Library',
            'total_hours_required' => 4.0,
            'hours_rendered' => 0,
            'start_date' => today()->toDateString(),
            'target_completion_date' => today()->addDays(14)->toDateString(),
            'status' => 'pending',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        StudentMonitoringFlag::create([
            'student_id' => $student->id,
            'flagged_by' => $officer->id,
            'flag_type' => 'registration_hold',
            'flag_reason' => 'Outstanding university service obligation.',
            'is_active' => true,
            'created_at' => now(),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'it03-supervisor@nu-clark.edu.ph',
            'password' => 'password',
        ])->assertOk()->json('access_token');

        $this->withToken($token)
            ->getJson('/api/mobile/us/assignments')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $assignment->id)
            ->assertJsonPath('data.0.studentId', '2026-30001')
            ->assertJsonPath('data.0.assignmentStatus', 'pending');

        $service = app(USAssignmentService::class);
        $startedAt = now()->subHours(4)->startOfSecond();

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_in',
            'occurred_at' => $startedAt->toIso8601String(),
            'totp' => $this->attendanceCode($assignment, $startedAt),
            'tasks_performed' => 'Shelving and inventory support.',
        ])
            ->assertCreated()
            ->assertJsonPath('totpVerified', true);

        $this->postSignedAttendance($assignment, $token, [
            'event_type' => 'time_out',
            'occurred_at' => now()->toIso8601String(),
            'totp' => $this->currentTotp($service, $assignment->fresh()),
        ])
            ->assertCreated()
            ->assertJsonPath('hoursRendered', 4)
            ->assertJsonPath('assignment.assignmentStatus', 'completed');

        $assignment->refresh();

        $this->assertSame('completed', $assignment->status);
        $this->assertSame('4.00', $assignment->hours_rendered);
        $this->assertSame('completed', $sanction->fresh()->status);
        $this->assertSame($supervisor->id, $sanction->fresh()->verified_by);
        $this->assertNotNull($sanction->fresh()->completed_at);

        $flag = StudentMonitoringFlag::where('student_id', $student->id)->firstOrFail();

        $this->assertFalse($flag->is_active);
        $this->assertNotNull($flag->deactivated_at);
        $this->assertDatabaseHas('notifications', [
            'type' => UniversityServiceStatusNotification::class,
            'notifiable_id' => $student->id,
        ]);
    }

    /**
     * IT-03-04 — Officer non-compliance action ↔ sanction status ↔ registration
     * hold ↔ RiskScoringService recomputation ↔ student notification.
     */
    public function test_marking_service_non_compliant_raises_a_hold_and_recomputes_the_risk_profile(): void
    {
        $officer = $this->user('discipline_officer', 'it03-nc-officer@nu-clark.edu.ph');
        $student = $this->student('it03-nc-student@students.nu-clark.edu.ph', '2026-30002');

        [$resolution, $sanction] = $this->serviceObligation($student, $officer, 'IT0304', 12.0);

        $assignment = UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'assigned_by' => $officer->id,
            'office_name' => 'Facilities Management Office',
            'total_hours_required' => 12.0,
            'hours_rendered' => 2.0,
            'start_date' => today()->subDays(40)->toDateString(),
            'target_completion_date' => today()->subDay()->toDateString(),
            'status' => 'in_progress',
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_secret_generated_at' => now(),
        ]);

        $this->actingAs($officer)
            ->from(route('officer.us.show', $assignment))
            ->post(route('officer.us.non-compliant', $assignment), [
                'reason' => 'The target completion date passed with 10 hours still outstanding.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $assignment->refresh();

        $this->assertSame('non_compliant', $assignment->status);
        $this->assertSame('non_compliant', $sanction->fresh()->status);
        $this->assertTrue(
            StudentMonitoringFlag::where('student_id', $student->id)
                ->where('flag_type', 'registration_hold')
                ->where('is_active', true)
                ->exists()
        );

        $snapshot = RiskScoreSnapshot::where('student_id', $student->id)
            ->where('trigger', 'sanction_non_compliance')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(1, $snapshot->contributing_factors['non_compliant_sanctions']['raw_value']);
        $this->assertGreaterThan(0, (float) $snapshot->composite_score);
        $this->assertDatabaseHas('notifications', [
            'type' => UniversityServiceStatusNotification::class,
            'notifiable_id' => $student->id,
        ]);
    }

    /**
     * IT-03-05 — Officer referral assignment ↔ guidance officer portal ↔
     * GuidanceReferralService outcome ↔ sanction status ↔ risk recomputation.
     */
    public function test_an_incomplete_counseling_referral_marks_the_sanction_non_compliant_and_notifies_the_student(): void
    {
        $officer = $this->user('discipline_officer', 'it03-ref-officer@nu-clark.edu.ph');
        $guidance = $this->user('guidance_officer', 'it03-guidance@nu-clark.edu.ph');
        $student = $this->student('it03-ref-student@students.nu-clark.edu.ph', '2026-30003');

        [$resolution] = $this->serviceObligation($student, $officer, 'IT0305', 6.0);

        $sanction = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'counseling_referral',
            'status' => 'pending',
        ]);

        // Assignment is automatic by department (see
        // GuidanceReferralService::matchOfficerForStudent()), so this test sets the
        // referral's Guidance Officer directly rather than through a manual-assign
        // endpoint that no longer exists -- the acknowledge/incomplete workflow below is
        // what this test actually verifies.
        $referral = CounselingReferral::create([
            'sanction_id' => $sanction->id,
            'student_id' => $student->id,
            'guidance_officer_id' => $guidance->id,
            'referred_by' => $officer->id,
            'referred_at' => now(),
            'referral_reason' => 'Assigned as part of a Category 2 disciplinary intervention.',
            'status' => 'pending',
        ]);

        $this->actingAs($guidance)
            ->post(route('guidance.referrals.acknowledge', $referral))
            ->assertRedirect(route('guidance.referrals.show', $referral))
            ->assertSessionHas('success');

        $this->assertNotNull($referral->fresh()->acknowledged_at);

        $this->actingAs($guidance)
            ->post(route('guidance.referrals.incomplete', $referral), [
                'reason' => 'The student did not attend any of the three scheduled counseling sessions.',
            ])
            ->assertRedirect(route('guidance.referrals.show', $referral))
            ->assertSessionHas('success');

        $referral->refresh();

        $this->assertSame('incomplete', $referral->status);
        $this->assertSame($guidance->id, $referral->confirmed_by);
        $this->assertSame('non_compliant', $sanction->fresh()->status);

        $snapshot = RiskScoreSnapshot::where('student_id', $student->id)
            ->where('trigger', 'counseling_non_compliance')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(1, $snapshot->contributing_factors['non_compliant_sanctions']['raw_value']);
        $this->assertDatabaseHas('notifications', [
            'type' => GuidanceReferralStatusNotification::class,
            'notifiable_id' => $student->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'counseling_referrals',
            'record_id' => $referral->id,
            'user_id' => $guidance->id,
        ]);
    }

    /** @return array{0: User, 1: User, 2: User, 3: DisciplinaryCase} */
    private function upccCaseAwaitingResolution(int $category, float $recommendedHours): array
    {
        $coordinator = $this->user('discipline_coordinator', 'it03-coordinator@nu-clark.edu.ph');
        $officer = $this->user('discipline_officer', 'it03-upcc-officer@nu-clark.edu.ph');
        $student = $this->student('it03-upcc-student@students.nu-clark.edu.ph', '2026-30099');

        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-11',
            'offense_type' => 'major',
            'title' => 'Serious misconduct within university premises',
            'description' => 'Serious misconduct within university premises.',
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '52',
            'keywords' => ['misconduct'],
            'created_at' => now(),
        ]);

        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id,
            'offense_count' => 1,
            'sanction_category' => $category,
            'us_hours_recommended' => $recommendedHours,
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-IT03UPCC',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'major',
            'case_type_locked' => true,
            'status' => 'pending_resolution',
            'confirmed_offense_id' => $offense->id,
            'assigned_sanction_category' => $category,
            'prior_offense_count' => 0,
            'forwarded_to_coordinator' => true,
            'forwarded_at' => now()->subDays(12),
        ]);

        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'prior_offense_count' => 0,
            'confirmed_by' => $officer->id,
            'confirmed_at' => now()->subDays(12),
        ]);

        UpccReferral::create([
            'case_id' => $case->id,
            'referred_by' => $coordinator->id,
            'referred_at' => now()->subDays(12),
            'scheduled_conference_at' => now()->subDays(6),
            'conference_location' => 'SDO Conference Room',
            'proceedings_type' => 'full_hearing',
            'status' => 'concluded',
            'decision_summary' => 'The UPCC found the student liable for the charged offense.',
            'decision_issued_at' => now()->subDays(5),
            'presidents_council_action' => 'approved',
            'presidents_council_acted_at' => now(),
            'decision_distributed_at' => now()->subDays(5),
            'appeal_window_start' => now()->subDays(5)->toDateString(),
            'appeal_window_end' => now()->subDay()->toDateString(),
            'appeal_filed' => false,
            'decision_final_at' => now()->subHours(4),
            'records_filed_at' => now()->subHours(4),
        ]);

        return [$coordinator, $officer, $student, $case->fresh()];
    }

    /** @return array{0: CaseResolution, 1: Sanction} */
    private function serviceObligation(User $student, User $officer, string $suffix, float $hours): array
    {
        $case = DisciplinaryCase::create([
            'case_number' => "DC-2026-{$suffix}",
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'minor',
            'case_type_locked' => true,
            'status' => 'resolved',
            'prior_offense_count' => 0,
            'resolved_at' => now(),
        ]);

        $resolution = CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => $hours,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => today()->addDays(5)->toDateString(),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);

        $sanction = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'university_service',
            'status' => 'pending',
        ]);

        return [$resolution, $sanction];
    }

    private function currentTotp(USAssignmentService $service, UsAssignment $assignment): string
    {
        return explode(':', $service->generateTotpUri($assignment))[3] ?? '';
    }

    private function user(string $role, string $email, array $extra = []): User
    {
        return User::create(array_merge([
            'last_name' => 'Villanueva',
            'first_name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ], $extra));
    }

    private function student(string $email, string $studentNumber): User
    {
        $student = $this->user('student', $email);

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => $studentNumber,
            'program' => 'BS Information Technology',
            'year_level' => '3',
        ]);

        return $student;
    }
}
