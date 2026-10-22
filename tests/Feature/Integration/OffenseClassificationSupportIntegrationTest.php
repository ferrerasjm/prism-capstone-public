<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\AcademicUnit;
use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\OffenseCatalog;
use App\Models\StudentProfile;
use App\Models\OffenseRecommendationAudit;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\SanctionMatrix;
use App\Models\User;
use App\Notifications\CaseEscalatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * INTEGRATION TEST GROUP 1 — Offense Classification and Escalation Support.
 *
 * Exercises the seam between the Discipline Officer web request pipeline, the
 * trained Tagalog RoBERTa hybrid offense-recommendation model (served over HTTP
 * by ml/scripts/serve_hybrid.py and faked here at the network boundary), the
 * keyword ClassificationEngine fallback, the offense recommendation audit trail,
 * CaseService, EscalationService and RiskScoringService.
 *
 * Run only this group:
 *   php artisan test tests/Feature/Integration/OffenseClassificationSupportIntegrationTest.php
 */
class OffenseClassificationSupportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const MODEL_URL = 'http://127.0.0.1:8765';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', self::MODEL_URL);
    }

    /**
     * IT-01-01 — Case review screen ↔ RoBERTa hybrid model service ↔ offense
     * catalog ↔ recommendation audit trail.
     */
    public function test_case_review_screen_routes_the_narrative_to_the_model_and_stores_an_advisory_audit(): void
    {
        [$officer, $student] = $this->actors();
        $this->offense('MAJOR-26', 'major', 'Possession of cigarettes and vapes', ['sigarilyo']);
        $this->offense('MINOR-04', 'minor', 'Inappropriate clothing graphics', ['graphic']);

        $this->fakeModel([
            ['rank' => 1, 'label' => 'MAJOR-26', 'score' => 0.87],
            ['rank' => 2, 'label' => 'MINOR-04', 'score' => 0.05],
        ]);

        $case = $this->openCase(
            $officer,
            $student,
            'IT0101',
            'Nahuli ang estudyante na may dalang sigarilyo sa loob ng campus grounds.'
        );

        $this->actingAs($officer)
            ->get(route('officer.cases.show', $case))
            ->assertOk();

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === self::MODEL_URL.'/predict'
            && $request['text'] === $case->incidentReport->description
            && $request['top_k'] === 4);

        $audit = OffenseRecommendationAudit::where('case_id', $case->id)->firstOrFail();

        $this->assertSame('hybrid_model', $audit->source);
        $this->assertSame('success', $audit->status);
        $this->assertSame('roberta-tfidf-svm-policy-hybrid', $audit->model_name);
        $this->assertSame('v3', $audit->model_version);
        $this->assertSame($officer->id, $audit->requested_by);
        $this->assertFalse($audit->no_match);
        $this->assertSame('MAJOR-26', $audit->recommendations[0]['label']);
        $this->assertSame(
            hash('sha256', $case->incidentReport->description),
            $audit->description_hash,
            'The raw narrative must never be stored in the model audit trail.'
        );

        // The model only advises — nothing may be charged without an officer decision.
        $this->assertNull($audit->selected_offense_id);
        $this->assertSame(0, $case->fresh()->caseOffenses()->count());
        $this->assertNull($case->fresh()->confirmed_offense_id);
    }

    /**
     * IT-01-02 — Model service outage ↔ keyword ClassificationEngine fallback ↔
     * recommendation audit trail.
     */
    public function test_model_outage_falls_back_to_the_keyword_engine_and_records_the_degraded_source(): void
    {
        [$officer, $student] = $this->actors();
        $this->offense('MINOR-01', 'minor', 'Non-wearing of the prescribed uniform', ['prescribed uniform']);

        Http::fake([self::MODEL_URL.'/predict' => Http::response([], 503)]);

        $case = $this->openCase(
            $officer,
            $student,
            'IT0102',
            'The student attended class without the prescribed uniform for a third consecutive day.'
        );

        $this->actingAs($officer)
            ->get(route('officer.cases.show', $case))
            ->assertOk();

        Http::assertSentCount(1);

        $audit = OffenseRecommendationAudit::where('case_id', $case->id)->firstOrFail();

        $this->assertSame('keyword_fallback', $audit->source);
        $this->assertSame('fallback', $audit->status);
        $this->assertNull($audit->model_name);
        $this->assertNull($audit->model_version);
        $this->assertNotEmpty($audit->recommendations);
        $this->assertContains('MINOR-01', array_column($audit->recommendations, 'label'));
        $this->assertSame(0, $case->fresh()->caseOffenses()->count());
    }

    /**
     * IT-01-03 — Officer override of the model ranking ↔ CaseService charge
     * confirmation ↔ decision audit ↔ sanction matrix category.
     */
    public function test_charging_an_offense_outside_the_model_ranking_requires_a_written_override_and_is_audited(): void
    {
        [$officer, $student] = $this->actors();
        $recommended = $this->offense('MINOR-04', 'minor', 'Inappropriate clothing graphics', ['graphic']);
        $chosen = $this->offense('MAJOR-26', 'major', 'Possession of cigarettes and vapes', ['sigarilyo']);
        SanctionMatrix::create([
            'offense_catalog_id' => $chosen->id,
            'offense_count' => 1,
            'sanction_category' => 3,
        ]);

        $this->fakeModel([
            ['rank' => 1, 'label' => 'MINOR-04', 'score' => 0.61],
        ]);

        $case = $this->openCase(
            $officer,
            $student,
            'IT0103',
            'The reported student wore a shirt with a graphic print and behaved uncooperatively.'
        );

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.confirm-offense', $case), ['offense_id' => $chosen->id])
            ->assertRedirect(route('officer.cases.show', $case))
            ->assertSessionHasErrors('override_reason');

        $this->assertSame(0, $case->fresh()->caseOffenses()->count());

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.confirm-offense', $case), [
                'offense_id' => $chosen->id,
                'override_reason' => 'Body-worn footage confirms possession of vape cartridges, not clothing graphics.',
            ])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel')
            ->assertSessionHas('success');

        $case->refresh();

        $this->assertSame('major', $case->case_type);
        $this->assertSame($chosen->id, $case->confirmed_offense_id);
        $this->assertSame(3, $case->suggested_sanction_category);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $case->id,
            'offense_catalog_id' => $chosen->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
        ]);

        $audit = OffenseRecommendationAudit::where('case_id', $case->id)->firstOrFail();

        $this->assertSame($chosen->id, $audit->selected_offense_id);
        $this->assertFalse($audit->decision_was_recommended);
        $this->assertSame($officer->id, $audit->decided_by);
        $this->assertNotNull($audit->decided_at);
        $this->assertSame('MINOR-04', $recommended->code);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'cases',
            'record_id' => $case->id,
            'user_id' => $officer->id,
            'action' => 'update',
        ]);
    }

    /**
     * IT-01-03a — Removing a wrongly charged offense ↔ CaseService charge
     * dismissal ↔ classification exclusion list.
     *
     * A dismissed charge is kept for the audit trail rather than deleted, so the
     * exclusion list that hides "already charged" offenses from the suggestion
     * ranking must key off the confirmed status only. Otherwise an offense the
     * officer removed disappears from the recommendations for good.
     */
    public function test_removing_a_charge_returns_that_offense_to_the_suggestion_ranking(): void
    {
        [$officer, $student] = $this->actors();
        $keep = $this->offense('MINOR-01', 'minor', 'Non-wearing of the prescribed uniform', ['prescribed uniform']);
        $removed = $this->offense('MINOR-04', 'minor', 'Inappropriate clothing graphics', ['graphic']);

        // Model outage forces the keyword engine, which applies the exclusion
        // list in-process where this bug lived.
        Http::fake([self::MODEL_URL.'/predict' => Http::response([], 503)]);

        $case = $this->openCase(
            $officer,
            $student,
            'IT0103a',
            'The student wore no prescribed uniform and a shirt with a graphic print.'
        );

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.confirm-offense', $case), [
                'offense_ids' => [$keep->id, $removed->id],
            ])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel');

        $charge = $case->fresh()->caseOffenses()->where('offense_catalog_id', $removed->id)->firstOrFail();

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->delete(route('officer.cases.offenses.destroy', [$case, $charge]), [
                'reason' => 'The graphic-print shirt was a printed club logo, which is not an offense.',
            ])
            ->assertRedirect();

        $this->assertSame('dismissed', $charge->fresh()->status);

        $response = $this->actingAs($officer)
            ->get(route('officer.cases.show', $case).'?add_offense=1')
            ->assertOk();

        // The removed offense is back in the ranking; the one still charged stays out.
        $response->assertSee('data-offense-id="'.$removed->id.'"', false);
        $response->assertDontSee('data-offense-id="'.$keep->id.'"', false);

        // Re-selecting it revives the dismissed row instead of hitting the
        // (case_id, offense_catalog_id) unique key.
        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case).'?add_offense=1')
            ->post(route('officer.cases.confirm-offense', $case), [
                'offense_ids' => [$removed->id],
            ])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel')
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $charge->fresh()->status);
        $this->assertNull($charge->fresh()->removed_at);
        $this->assertSame(
            1,
            $case->fresh()->caseOffenses()->where('offense_catalog_id', $removed->id)->count(),
            'Re-adding a removed offense must not create a second charge row.'
        );
        $this->assertSame(2, $case->fresh()->caseOffenses()->where('status', 'confirmed')->count());
    }

    /**
     * IT-01-04 — Super Admin user management ↔ department/role assignment ↔
     * authentication and authorization middleware ↔ protected classification feature.
     *
     * Permission state is not static in PRISM: an administrator can move an officer
     * between departments or deactivate the account, and the officer's authenticated
     * access to the classification pipeline must follow. Access control is what
     * decides whether the model is consulted at all, so it is verified against that
     * pipeline rather than in isolation.
     */
    public function test_admin_department_and_status_changes_take_effect_on_the_officers_authenticated_access(): void
    {
        // Academic units/programs are seeded by the academic_programs migration itself
        // (from config('academics.programs')) under RefreshDatabase, so this scenario
        // reuses two of the already-seeded units instead of inserting duplicate names
        // and tripping the unique constraint on academic_programs.name.
        $computing = AcademicUnit::where('name', 'School of Engineering, Architecture, and Technology')->firstOrFail();
        $business = AcademicUnit::where('name', 'School of Accountancy, Business, and Management')->firstOrFail();

        $admin = User::create([
            'last_name' => 'Admin',
            'first_name' => 'Super',
            'email' => 'it0104-demo-user-001@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Reyes',
            'first_name' => 'Discipline',
            'email' => 'it0104-officer@nu-clark.edu.ph',
            'institutional_id' => 'DO-0104',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
            'academic_unit_id' => $computing->id,
        ]);

        $computingStudent = $this->departmentStudent('it0104-computing@students.nu-clark.edu.ph', '2026-11001', 'BS Information Technology');
        $businessStudent = $this->departmentStudent('it0104-business@students.nu-clark.edu.ph', '2026-11002', 'BS Accountancy');

        $minor = $this->offense('MINOR-01', 'minor', 'Non-wearing of the prescribed uniform', ['prescribed uniform']);
        $this->fakeModel([
            ['rank' => 1, 'label' => 'MINOR-01', 'score' => 0.88],
        ]);

        $computingCase = $this->openCase($officer, $computingStudent, 'IT0104A', 'The student was reported without the prescribed uniform.');
        $businessCase = $this->openCase($officer, $businessStudent, 'IT0104B', 'The student was reported without the prescribed uniform.');

        // Assigned department: the case opens and the model is consulted.
        $this->actingAs($officer)
            ->get(route('officer.cases.show', $computingCase))
            ->assertOk();

        // Outside the assigned department: blocked before any classification work happens.
        $this->actingAs($officer)
            ->get(route('officer.cases.show', $businessCase))
            ->assertForbidden();

        $this->actingAs($officer)
            ->post(route('officer.cases.confirm-offense', $businessCase), ['offense_id' => $minor->id])
            ->assertForbidden();

        Http::assertSentCount(1);
        $this->assertSame(1, OffenseRecommendationAudit::where('case_id', $computingCase->id)->count());
        $this->assertSame(0, OffenseRecommendationAudit::where('case_id', $businessCase->id)->count());
        $this->assertSame(0, $businessCase->fresh()->caseOffenses()->count());

        // Neither the officer nor a student may reach user management itself.
        $this->actingAs($officer)
            ->get(route('admin.users.index'))
            ->assertForbidden();
        $this->actingAs($computingStudent)
            ->get(route('officer.cases.show', $computingCase))
            ->assertForbidden();

        // The Super Admin reassigns the officer to the other academic department.
        $this->actingAs($admin)
            ->from(route('admin.users.show', $officer))
            ->patch(route('admin.users.administrative-details.update', $officer), [
                'institutional_id' => 'DO-0104',
                'email' => $officer->email,
                'academic_unit_ids' => [$business->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'users',
            'record_id' => $officer->id,
        ]);

        // The authenticated officer's access now reflects the new assignment in both directions.
        $reassigned = $officer->fresh();

        $this->actingAs($reassigned)
            ->get(route('officer.cases.show', $businessCase))
            ->assertOk();

        $this->actingAs($reassigned)
            ->get(route('officer.cases.show', $computingCase))
            ->assertForbidden();

        $this->assertSame(1, OffenseRecommendationAudit::where('case_id', $businessCase->id)->count());

        // Deactivating the account revokes access on the officer's next request.
        $this->actingAs($admin)
            ->from(route('admin.users.show', $officer))
            ->patch(route('admin.users.toggle-status', $officer), [
                'reason' => 'Officer reassigned off the Student Discipline Office roster.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('inactive', $officer->fresh()->status);

        $this->actingAs($officer->fresh())
            ->get(route('officer.cases.show', $businessCase))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * IT-01-05 — Model-recommended repeat minor findings ↔ EscalationService
     * habitual-disregard rule ↔ CaseService MAJOR-19 escalation ↔ RiskScoringService.
     */
    public function test_repeat_model_recommended_findings_raise_habitual_disregard_and_recompute_the_risk_profile(): void
    {
        [$officer, $student] = $this->actors();
        $uniform = $this->offense('MINOR-01', 'minor', 'Non-wearing of the prescribed uniform', ['prescribed uniform']);
        $major19 = $this->offense('MAJOR-19', 'major', 'Habitual disregard of university rules', ['habitual disregard']);
        SanctionMatrix::create([
            'offense_catalog_id' => $major19->id,
            'offense_count' => 1,
            'sanction_category' => 3,
            'us_hours_recommended' => 24.0,
        ]);

        foreach ([1, 2] as $sequence) {
            $prior = DisciplinaryCase::create([
                'case_number' => "DC-2026-IT0105-{$sequence}",
                'student_id' => $student->id,
                'assigned_to' => $officer->id,
                'case_type' => 'minor',
                'case_type_locked' => true,
                'status' => 'resolved',
                'confirmed_offense_id' => $uniform->id,
                'prior_offense_count' => $sequence - 1,
                'resolved_at' => now()->subMonths($sequence),
            ]);
            $prior->caseOffenses()->create([
                'offense_catalog_id' => $uniform->id,
                'status' => 'confirmed',
                'prior_offense_count' => $sequence - 1,
                'confirmed_by' => $officer->id,
                'confirmed_at' => now()->subMonths($sequence),
            ]);
        }

        $this->fakeModel([
            ['rank' => 1, 'label' => 'MINOR-01', 'score' => 0.93],
        ]);

        $case = $this->openCase(
            $officer,
            $student,
            'IT0105',
            'The student was again reported for not wearing the prescribed uniform inside the academic building.'
        );

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.confirm-offense', $case), ['offense_id' => $uniform->id])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel')
            ->assertSessionHas('success');

        $case->refresh();

        $this->assertTrue((bool) $case->escalation_flagged);
        $this->assertStringContainsString('Third offense of the same type', (string) $case->escalation_flag_reason);
        $this->assertNull($case->escalation_acknowledged_at);

        $flaggedSnapshot = RiskScoreSnapshot::where('student_id', $student->id)
            ->where('trigger', 'case_escalation_flagged')
            ->latest('id')
            ->first();

        $this->assertNotNull($flaggedSnapshot, 'Raising an escalation flag must recompute the behavioural risk profile.');
        $this->assertTrue($flaggedSnapshot->contributing_factors['escalation_flag']['triggered']);

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.apply-escalation', $case))
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel')
            ->assertSessionHas('success');

        $case->refresh();

        $this->assertSame('major', $case->case_type);
        $this->assertSame($major19->id, $case->confirmed_offense_id);
        $this->assertSame(3, $case->suggested_sanction_category);
        $this->assertNotNull($case->escalation_acknowledged_at);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $case->id,
            'offense_catalog_id' => $major19->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $case->id,
            'offense_catalog_id' => $uniform->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => CaseEscalatedNotification::class,
            'notifiable_id' => $student->id,
        ]);
        $this->assertNotNull(RiskScore::where('student_id', $student->id)->first());
    }

    /** @return array{0: User, 1: User} */
    private function actors(): array
    {
        return [
            User::create([
                'last_name' => 'Reyes',
                'first_name' => 'Discipline',
                'email' => 'it01-officer@nu-clark.edu.ph',
                'password' => Hash::make('password'),
                'role' => 'discipline_officer',
                'status' => 'active',
            ]),
            User::create([
                'last_name' => 'Santos',
                'first_name' => 'Marco',
                'email' => 'it01-student@students.nu-clark.edu.ph',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
            ]),
        ];
    }

    private function departmentStudent(string $email, string $studentNumber, string $program): User
    {
        $student = User::create([
            'last_name' => 'Santos',
            'first_name' => 'Marco',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => $studentNumber,
            'program' => $program,
            'year_level' => '3',
        ]);

        return $student;
    }

    private function offense(string $code, string $type, string $title, array $keywords): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => $title,
            'description' => $title.'.',
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '48',
            'keywords' => $keywords,
            'created_at' => now(),
        ]);
    }

    private function openCase(User $officer, User $student, string $suffix, string $description): DisciplinaryCase
    {
        $report = IncidentReport::create([
            'report_number' => "IR-2026-{$suffix}",
            'source_type' => 'faculty_report',
            'reported_by_user_id' => $officer->id,
            'reported_by_name' => $officer->full_name,
            'student_id' => $student->id,
            'date_of_incident' => now()->subDay()->toDateString(),
            'description' => $description,
            'violation_type' => 'Others',
            'status' => 'under_review',
        ]);

        return DisciplinaryCase::create([
            'case_number' => "DC-2026-{$suffix}",
            'incident_report_id' => $report->id,
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => null,
            'case_type_locked' => false,
            'status' => 'open',
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
        ]);
    }

    private function fakeModel(array $predictions, bool $noMatch = false): void
    {
        Http::fake([
            self::MODEL_URL.'/predict' => Http::response([
                'advisory' => true,
                'model' => [
                    'name' => 'roberta-tfidf-svm-policy-hybrid',
                    'version' => 'v3',
                ],
                'no_match' => $noMatch,
                'guardrail' => ['applied' => false, 'reason' => null],
                'predictions' => $predictions,
            ]),
        ]);
    }
}
