<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IncidentReport;
use App\Models\OffenseCatalog;
use App\Models\SanctionMatrix;
use App\Models\User;
use App\Services\CaseService;
use App\Services\IncidentReportService;
use App\Services\SanctionRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the two gaps identified in the case workflow review: mutating actions
 * that relied only on the UI to hide themselves once a case moved on (now
 * enforced by CaseService itself), and the two genuine "I made a mistake"
 * corrections that previously had no path except voiding the whole case.
 */
class CaseCorrectionActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wrongly_confirmed_offense_can_be_removed_while_the_case_is_still_open(): void
    {
        $officer = $this->officer();
        $student = $this->student();
        $caseService = new CaseService;

        $minor = $this->offense('MINOR-REMOVE', 'minor');
        $major = $this->offense('MAJOR-REMOVE', 'major');
        SanctionMatrix::create(['offense_catalog_id' => $major->id, 'offense_count' => 1, 'sanction_category' => 2]);

        $case = $this->openCase($officer, $student);

        $caseService->confirmOffense($case->fresh(), $minor->id, $officer->id);
        $caseService->confirmOffense($case->fresh(), $major->id, $officer->id);

        $case = $case->fresh(['caseOffenses']);
        $this->assertSame('major', $case->case_type, 'Adding a major charge should classify the case as major.');
        $wrongCharge = $case->caseOffenses->firstWhere('offense_catalog_id', $major->id);

        $caseService->removeOffenseCharge($case, $wrongCharge, 'Wrong offense selected by mistake, student was not involved in this charge.', $officer->id);

        $case = $case->fresh(['caseOffenses']);
        $this->assertSame('minor', $case->case_type, 'Removing the only major charge should reclassify the case as minor.');
        $this->assertSame($minor->id, $case->confirmed_offense_id);
        $this->assertSame('dismissed', $wrongCharge->fresh()->status);
        $this->assertSame($officer->id, $wrongCharge->fresh()->removed_by);
        $this->assertNotNull($wrongCharge->fresh()->removed_at);
        $this->assertTrue(
            AuditLog::where('record_id', $case->id)->where('module', 'cases')
                ->where('reason', 'like', 'Wrong offense selected%')
                ->exists()
        );
    }

    public function test_an_offense_cannot_be_removed_once_the_case_type_is_locked(): void
    {
        $officer = $this->officer();
        $student = $this->student();
        $caseService = new CaseService;

        $major = $this->offense('MAJOR-LOCKED', 'major');
        SanctionMatrix::create(['offense_catalog_id' => $major->id, 'offense_count' => 1, 'sanction_category' => 2]);

        $case = $this->openCase($officer, $student);
        $caseService->confirmOffense($case->fresh(), $major->id, $officer->id);
        $caseService->confirmSanction($case->fresh(), 2, $officer->id);
        $caseService->issueNTE($case->fresh(), ['incident_date' => today()->toDateString()], $officer->id);

        $case = $case->fresh(['caseOffenses']);
        $charge = $case->caseOffenses->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Offense charges cannot be changed after the case type is locked.');

        $caseService->removeOffenseCharge($case, $charge, 'Trying to remove after the notice already went out.', $officer->id);
    }

    public function test_removing_an_offense_over_http_requires_a_reason_and_then_succeeds(): void
    {
        $officer = $this->officer();
        $student = $this->student();
        $caseService = new CaseService;

        $minor = $this->offense('MINOR-HTTP', 'minor');
        $extra = $this->offense('MINOR-HTTP-2', 'minor');
        $case = $this->openCase($officer, $student);
        $caseService->confirmOffense($case->fresh(), $minor->id, $officer->id);
        $caseService->confirmOffense($case->fresh(), $extra->id, $officer->id);

        $case = $case->fresh(['caseOffenses']);
        $charge = $case->caseOffenses->firstWhere('offense_catalog_id', $extra->id);

        $this->actingAs($officer)
            ->delete(route('officer.cases.offenses.destroy', [$case, $charge]), ['reason' => 'short'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('confirmed', $charge->fresh()->status);

        $this->actingAs($officer)
            ->delete(route('officer.cases.offenses.destroy', [$case, $charge]), [
                'reason' => 'This second offense was added to the wrong case by mistake.',
            ])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel');

        $this->assertSame('dismissed', $charge->fresh()->status);
    }

    public function test_an_evaluation_can_be_corrected_while_the_case_is_under_review(): void
    {
        [$case, $officer, $caseService] = $this->caseUnderReview();

        $caseService->correctEvaluation(
            $case->fresh(),
            $case->explanation,
            'full_denial',
            'Re-read the explanation and the student actually denies the allegation.',
            'Misread the explanation the first time — corrected after a second review.',
            $officer->id,
            new SanctionRecommendationService
        );

        $case = $case->fresh(['explanation']);
        $this->assertSame('full_denial', $case->explanation->admission_type);
        $this->assertSame('upcc_referral', $case->path_recommendation, 'A denial for a category 2 offense should recommend UPCC referral.');
        $this->assertTrue(
            AuditLog::where('record_id', $case->id)->where('module', 'cases')
                ->where('reason', 'like', 'Misread the explanation%')
                ->exists()
        );
    }

    public function test_an_evaluation_cannot_be_corrected_once_resolution_has_been_initiated(): void
    {
        [$case, $officer, $caseService] = $this->caseUnderReview();

        $caseService->initiateResolution($case->fresh(), $officer->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The evaluation can only be corrected while the case is under review.');

        $caseService->correctEvaluation(
            $case->fresh(),
            $case->explanation,
            'full_denial',
            null,
            'Trying to correct after resolution already started.',
            $officer->id,
            new SanctionRecommendationService
        );
    }

    public function test_offense_charges_cannot_be_added_to_a_case_that_is_already_resolved(): void
    {
        $officer = $this->officer();
        $student = $this->student();
        $caseService = new CaseService;

        $minor = $this->offense('MINOR-RESOLVED', 'minor');
        $case = $this->openCase($officer, $student);
        $caseService->confirmOffense($case->fresh(), $minor->id, $officer->id);
        $caseService->resolveMinorCase($case->fresh(), 'Verbal warning issued and logged.', $officer->id);

        $this->assertSame('resolved', $case->fresh()->status);

        $another = $this->offense('MINOR-RESOLVED-2', 'minor');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Offense charges can only be changed while the case is still open.');

        $caseService->confirmOffense($case->fresh(), $another->id, $officer->id);
    }

    public function test_a_resolved_case_cannot_be_dismissed_again(): void
    {
        $officer = $this->officer();
        $student = $this->student();
        $caseService = new CaseService;

        $minor = $this->offense('MINOR-DISMISS-TWICE', 'minor');
        $case = $this->openCase($officer, $student);
        $caseService->confirmOffense($case->fresh(), $minor->id, $officer->id);
        $caseService->resolveMinorCase($case->fresh(), 'Verbal warning issued and logged.', $officer->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This case is already closed.');

        $caseService->dismiss($case->fresh(), 'Trying to dismiss an already-resolved case.', $officer->id);
    }

    public function test_a_resolved_case_cannot_be_voided(): void
    {
        $officer = $this->officer();
        $student = $this->student();
        $caseService = new CaseService;

        $minor = $this->offense('MINOR-VOID-CLOSED', 'minor');
        $case = $this->openCase($officer, $student);
        $caseService->confirmOffense($case->fresh(), $minor->id, $officer->id);
        $caseService->resolveMinorCase($case->fresh(), 'Verbal warning issued and logged.', $officer->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This case is already closed.');

        $caseService->requestVoid($case->fresh(), 'Trying to void an already-resolved case.', $officer->id);
    }

    public function test_a_void_request_cannot_be_submitted_twice_while_pending_coordinator_approval(): void
    {
        [$case, $officer, $caseService] = $this->caseUnderReview();

        // under_review is past the open/pending_nte boundary, so this void needs Coordinator approval
        // rather than taking effect immediately.
        $requiresApproval = ! $caseService->requestVoid($case->fresh(), 'First void request, needs Coordinator approval.', $officer->id);
        $this->assertTrue($requiresApproval);
        $this->assertTrue($case->fresh()->void_requires_coordinator_approval);
        $this->assertSame('under_review', $case->fresh()->status, 'The case stays in its current status while the void request awaits approval.');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A void request for this case is already pending Coordinator approval.');

        $caseService->requestVoid($case->fresh(), 'Second void request while the first is still pending.', $officer->id);
    }

    public function test_sanction_category_cannot_be_changed_once_the_case_leaves_open(): void
    {
        $officer = $this->officer();
        $student = $this->student();
        $caseService = new CaseService;

        $major = $this->offense('MAJOR-SANCTION-LOCKED', 'major');
        SanctionMatrix::create(['offense_catalog_id' => $major->id, 'offense_count' => 1, 'sanction_category' => 2]);

        $case = $this->openCase($officer, $student);
        $caseService->confirmOffense($case->fresh(), $major->id, $officer->id);
        $caseService->confirmSanction($case->fresh(), 2, $officer->id);
        $caseService->issueNTE($case->fresh(), ['incident_date' => today()->toDateString()], $officer->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The sanction category can only be set while the case is still open.');

        $caseService->confirmSanction($case->fresh(), 3, $officer->id);
    }

    /** @return array{0: \App\Models\DisciplinaryCase, 1: User, 2: CaseService} */
    private function caseUnderReview(): array
    {
        $officer = $this->officer();
        $student = $this->student();
        $caseService = new CaseService;

        $major = $this->offense('MAJOR-EVAL-CORRECT', 'major');
        SanctionMatrix::create(['offense_catalog_id' => $major->id, 'offense_count' => 1, 'sanction_category' => 2]);

        $case = $this->openCase($officer, $student);
        $caseService->confirmOffense($case->fresh(), $major->id, $officer->id);
        $caseService->confirmSanction($case->fresh(), 2, $officer->id);
        $caseService->issueNTE($case->fresh(), ['incident_date' => today()->toDateString()], $officer->id);

        $this->actingAs($student)
            ->post(route('student.cases.explanation.store', $case->fresh()), [
                'explanation_text' => 'I admit to the incident and take full responsibility for what happened.',
            ]);

        $case = $case->fresh(['explanation']);

        $caseService->saveEvaluation(
            $case,
            $case->explanation,
            'full_admission',
            'Initial read: full admission.',
            $officer->id,
            new SanctionRecommendationService
        );

        return [$case->fresh(['explanation']), $officer, $caseService];
    }

    private function openCase(User $officer, User $student): \App\Models\DisciplinaryCase
    {
        $report = IncidentReport::create([
            'report_number' => 'IR-CORR-'.uniqid(),
            'source_type' => 'direct_encoding',
            'reported_by_user_id' => $officer->id,
            'student_id' => $student->id,
            'date_of_incident' => today(),
            'description' => 'Report used to exercise case correction actions.',
            'status' => 'under_review',
        ]);

        return (new IncidentReportService)->convertToCase($report, $officer)[0];
    }

    private function offense(string $code, string $type): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => $code.' title',
            'description' => 'Offense used for case correction coverage.',
            'keywords' => ['correction'],
            'created_at' => now(),
        ]);
    }

    private function officer(): User
    {
        return User::create([
            'last_name' => 'Officer',
            'first_name' => 'Correction',
            'email' => 'correction-officer-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
    }

    private function student(): User
    {
        return User::create([
            'last_name' => 'Student',
            'first_name' => 'Correction',
            'email' => 'correction-student-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);
    }
}
