<?php

declare(strict_types=1);

namespace Tests\Unit\ViolationCaseDocumentation;

use App\Models\CaseExplanation;
use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Module 2 - Violation and Case Documentation
 * Component: Notice to Explain Correction Controls
 *            (App\Services\CaseService::voidNTE / extendNTEDeadline / reissueNTE)
 * Test Cycle No. 1 - 5 test cases.
 */
class NteCorrectionControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Carbon::setTestNow('2026-08-03 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Scenario 1 - Normal: voiding an active NTE returns the case to the open stage. */
    public function test_voiding_an_active_nte_returns_the_case_to_the_open_stage(): void
    {
        [$case, $officer, $notice] = $this->issuedNte();

        (new CaseService)->voidNTE($case->fresh(), $notice, 'Wrong incident date encoded.', $officer->id);

        $this->assertTrue($notice->fresh()->is_voided);
        $this->assertSame($officer->id, $notice->fresh()->voided_by);
        $this->assertSame('Wrong incident date encoded.', $notice->fresh()->void_reason);
        $this->assertSame('open', $case->fresh()->status);
    }

    /** Scenario 2 - Failure: an already-voided notice cannot be changed again. */
    public function test_an_already_voided_notice_cannot_be_changed_again(): void
    {
        [$case, $officer, $notice] = $this->issuedNte();
        (new CaseService)->voidNTE($case->fresh(), $notice, 'First correction.', $officer->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only the active NTE of a case awaiting response can be changed.');

        (new CaseService)->voidNTE($case->fresh(), $notice->fresh(), 'Second correction.', $officer->id);
    }

    /** Scenario 3 - Failure: once the student has answered, the notice is frozen. */
    public function test_once_the_student_has_answered_the_notice_is_frozen(): void
    {
        [$case, $officer, $notice] = $this->issuedNte();

        CaseExplanation::create([
            'case_id' => $case->id,
            'submitted_at' => now(),
            'submission_channel' => 'portal',
            'admission_type' => 'full_admission',
            'explanation_text' => 'The student has already submitted a written explanation.',
            'word_count' => 9,
            'created_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only the active NTE of a case awaiting response can be changed.');

        (new CaseService)->voidNTE($case->fresh(), $notice, 'Too late to correct.', $officer->id);
    }

    /** Scenario 4 - Boundary: an extension must move the deadline forward, never backward. */
    public function test_an_extension_must_move_the_deadline_forward(): void
    {
        [$case, $officer, $notice] = $this->issuedNte();
        $service = new CaseService;

        $service->extendNTEDeadline(
            $case->fresh(),
            $notice,
            Carbon::parse('2026-08-12'),
            'Student is on approved medical leave.',
            $officer->id
        );

        $this->assertSame('2026-08-12', $notice->fresh()->response_deadline->toDateString());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The extended deadline must be after the current deadline.');

        $service->extendNTEDeadline(
            $case->fresh(),
            $notice->fresh(),
            Carbon::parse('2026-08-10'),
            'Attempt to pull the deadline back.',
            $officer->id
        );
    }

    /** Scenario 5 - Normal: reissuing voids the old notice and issues a fresh one. */
    public function test_reissuing_voids_the_old_notice_and_issues_a_fresh_one(): void
    {
        [$case, $officer, $notice] = $this->issuedNte();

        $replacement = (new CaseService)->reissueNTE(
            $case->fresh(),
            $notice,
            ['incident_date' => '2026-07-31', 'additional_content' => 'Corrected incident date.'],
            'Incident date corrected after review.',
            $officer->id
        );

        $this->assertTrue($notice->fresh()->is_voided);
        $this->assertFalse($replacement->is_voided);
        $this->assertNotSame($notice->id, $replacement->id);
        $this->assertSame('2026-07-31', $replacement->incident_date->toDateString());
        $this->assertSame('pending_nte', $case->fresh()->status);
        $this->assertSame(1, CaseNotice::where('case_id', $case->id)->where('is_voided', false)->count());
    }

    /** @return array{0: DisciplinaryCase, 1: User, 2: CaseNotice} */
    private function issuedNte(): array
    {
        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Charged',
            'email' => 'ntefix.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Issuing',
            'email' => 'ntefix.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-15',
            'offense_type' => 'major',
            'title' => 'Vandalism of university property',
            'description' => 'Vandalism of university property.',
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '36',
            'keywords' => ['vandalism'],
            'created_at' => now(),
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-7101',
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'open',
            'confirmed_offense_id' => $offense->id,
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
        ]);

        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);

        $notice = (new CaseService)->issueNTE(
            $case->fresh(),
            ['incident_date' => '2026-07-30', 'additional_content' => 'Initial notice.'],
            $officer->id
        );

        return [$case->fresh(), $officer, $notice];
    }
}
