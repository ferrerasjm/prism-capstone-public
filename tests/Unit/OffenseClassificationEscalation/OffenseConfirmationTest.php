<?php

declare(strict_types=1);

namespace Tests\Unit\OffenseClassificationEscalation;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\SanctionMatrix;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Offense Confirmation and Case Typing
 *            (App\Services\CaseService::confirmOffenses)
 * Test Cycle No. 1 - 5 test cases.
 *
 * Confirming charges is the point at which an advisory recommendation
 * becomes an official classification: it fixes the primary charge, the
 * suggested sanction category and the case type.
 */
class OffenseConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /** Scenario 1 - Normal: confirming one minor charge types the case as minor. */
    public function test_confirming_a_single_minor_charge_types_the_case_as_minor(): void
    {
        [$case, $officer] = $this->openCase();
        $minor = $this->offense('MINOR-01', 'minor', 1, 1);

        (new CaseService)->confirmOffenses($case, [$minor->id], $officer->id);

        $fresh = $case->fresh();
        $this->assertSame('minor', $fresh->case_type);
        $this->assertSame($minor->id, $fresh->confirmed_offense_id);
        $this->assertSame(1, $fresh->suggested_sanction_category);
        $this->assertSame(0, $fresh->prior_offense_count);
    }

    /** Scenario 2 - Boundary: a mixed set of charges types the whole case as major. */
    public function test_a_mixed_set_of_charges_types_the_whole_case_as_major(): void
    {
        [$case, $officer] = $this->openCase();
        $minor = $this->offense('MINOR-01', 'minor', 1, 1);
        $major = $this->offense('MAJOR-15', 'major', 1, 3);

        (new CaseService)->confirmOffenses($case, [$minor->id, $major->id], $officer->id);

        $fresh = $case->fresh();
        $this->assertSame('major', $fresh->case_type);
        $this->assertSame(2, $fresh->caseOffenses()->where('status', 'confirmed')->count());
    }

    /** Scenario 3 - Normal: the charge carrying the highest sanction category becomes primary. */
    public function test_the_charge_with_the_highest_sanction_category_becomes_primary(): void
    {
        [$case, $officer] = $this->openCase();
        $lower = $this->offense('MAJOR-10', 'major', 1, 2);
        $higher = $this->offense('MAJOR-15', 'major', 1, 4);

        (new CaseService)->confirmOffenses($case, [$lower->id, $higher->id], $officer->id);

        $fresh = $case->fresh();
        $this->assertSame($higher->id, $fresh->confirmed_offense_id);
        $this->assertSame(4, $fresh->suggested_sanction_category);
    }

    /** Scenario 4 - Failure: the same offense cannot be charged twice on one case. */
    public function test_the_same_offense_cannot_be_charged_twice_on_one_case(): void
    {
        [$case, $officer] = $this->openCase();
        $minor = $this->offense('MINOR-01', 'minor', 1, 1);
        (new CaseService)->confirmOffenses($case, [$minor->id], $officer->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('One or more selected offenses are already an active charge on this case.');

        (new CaseService)->confirmOffenses($case->fresh(), [$minor->id], $officer->id);
    }

    /** Scenario 5 - Failure: an empty selection, and a locked case, are both refused. */
    public function test_an_empty_selection_and_a_locked_case_are_both_refused(): void
    {
        [$case, $officer] = $this->openCase();
        $minor = $this->offense('MINOR-01', 'minor', 1, 1);

        try {
            (new CaseService)->confirmOffenses($case, [], $officer->id);
            $this->fail('An empty offense selection must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Select at least one offense to confirm.', $exception->getMessage());
        }

        $case->update(['case_type_locked' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Offense charges cannot be changed after the case type is locked.');

        (new CaseService)->confirmOffenses($case->fresh(), [$minor->id], $officer->id);
    }

    private function offense(string $code, string $type, int $offenseCount, int $category): OffenseCatalog
    {
        $offense = OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => "Catalog entry {$code}",
            'description' => "Catalog entry {$code}.",
            'handbook_section' => $type === 'minor' ? 'Section VI.D' : 'Section VI.E',
            'handbook_page' => '35',
            'keywords' => [],
            'created_at' => now(),
        ]);

        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id,
            'offense_count' => $offenseCount,
            'sanction_category' => $category,
            'us_hours_recommended' => 6.0,
            'us_hours_min' => 6.0,
            'us_hours_max' => 6.0,
            'us_hours_guidance' => '6 hours of University Service.',
            'notes' => null,
        ]);

        return $offense;
    }

    /** @return array{0: DisciplinaryCase, 1: User} */
    private function openCase(): array
    {
        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Charged',
            'email' => 'confirm.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Confirming',
            'email' => 'confirm.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-7201',
            'student_id' => $student->id,
            'case_type' => null,
            'case_type_locked' => false,
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
