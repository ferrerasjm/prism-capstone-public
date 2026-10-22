<?php

declare(strict_types=1);

namespace Tests\Unit\ViolationCaseDocumentation;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Services\CaseService;
use App\Services\MajorOffenseWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Module 2 - Violation and Case Documentation
 * Component: Notice to Explain Issuance (App\Services\CaseService::issueNTE)
 * Test Cycle No. 1 - 5 test cases.
 */
class NteIssuanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Scenario 1 - Failure: an NTE cannot be issued before an offense is confirmed. */
    public function test_an_nte_cannot_be_issued_before_an_offense_is_confirmed(): void
    {
        [$case] = $this->caseWithoutOffense();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot issue NTE: no confirmed offense on this case.');

        (new CaseService)->issueNTE($case, $this->noticeData(), 1);
    }

    /** Scenario 2 - Normal: issuing an NTE sets the five-day response deadline. */
    public function test_issuing_an_nte_sets_the_five_day_response_deadline(): void
    {
        Carbon::setTestNow('2026-08-03 14:20:00');
        [$case, $officer] = $this->caseWithConfirmedOffense();

        $notice = (new CaseService)->issueNTE($case, $this->noticeData(), $officer->id);

        $this->assertSame('notice_to_explain', $notice->notice_type);
        $this->assertSame('2026-08-08', $notice->response_deadline->toDateString());
        $this->assertSame(
            MajorOffenseWorkflow::nteResponseDeadline(Carbon::parse('2026-08-03 14:20:00'))->toDateString(),
            $notice->response_deadline->toDateString()
        );
        $this->assertFalse($notice->is_voided);
    }

    /** Scenario 3 - Normal: issuing an NTE moves the case forward and locks its type. */
    public function test_issuing_an_nte_moves_the_case_forward_and_locks_its_type(): void
    {
        [$case, $officer] = $this->caseWithConfirmedOffense();

        (new CaseService)->issueNTE($case, $this->noticeData(), $officer->id);

        $this->assertSame('pending_nte', $case->fresh()->status);
        $this->assertTrue($case->fresh()->case_type_locked);
    }

    /** Scenario 4 - Normal: the notice carries the confirmed charges and handbook reference. */
    public function test_the_notice_carries_the_confirmed_charges_and_handbook_reference(): void
    {
        [$case, $officer, $offense] = $this->caseWithConfirmedOffense();

        $notice = (new CaseService)->issueNTE($case, $this->noticeData(), $officer->id);
        $charges = $notice->additional_content['offenses'];

        $this->assertCount(1, $charges);
        $this->assertSame($offense->id, $charges[0]['id']);
        $this->assertSame('MAJOR-15', $charges[0]['code']);
        $this->assertSame('Section VI.E', $notice->handbook_section);
        $this->assertSame('Reported by the Security Office.', $notice->additional_content['notes']);
    }

    /** Scenario 5 - Boundary: the notice is recorded against the case and its issuing officer. */
    public function test_the_notice_is_recorded_against_the_case_and_its_issuing_officer(): void
    {
        [$case, $officer] = $this->caseWithConfirmedOffense();

        $notice = (new CaseService)->issueNTE($case, $this->noticeData(), $officer->id);

        $this->assertSame(1, CaseNotice::where('case_id', $case->id)->count());
        $this->assertSame($officer->id, $notice->issued_by);
        $this->assertSame('2026-07-30', $notice->incident_date->toDateString());
    }

    private function noticeData(): array
    {
        return [
            'incident_date' => '2026-07-30',
            'additional_content' => 'Reported by the Security Office.',
        ];
    }

    /** @return array{0: DisciplinaryCase, 1: User, 2: OffenseCatalog} */
    private function caseWithConfirmedOffense(): array
    {
        [$case, $officer] = $this->caseWithoutOffense();

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

        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);

        $case->update(['confirmed_offense_id' => $offense->id, 'case_type' => 'major']);

        return [$case->fresh(), $officer, $offense];
    }

    /** @return array{0: DisciplinaryCase, 1: User} */
    private function caseWithoutOffense(): array
    {
        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Charged',
            'email' => 'nte.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Issuing',
            'email' => 'nte.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-7001',
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
        ]);

        return [$case, $officer];
    }
}
