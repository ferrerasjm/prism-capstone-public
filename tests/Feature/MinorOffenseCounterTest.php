<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MinorOffenseCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_counter_separates_resolved_findings_from_active_charges_and_updates_after_resolution(): void
    {
        $officer = $this->user('discipline_officer', 'counter-officer@example.test');
        $otherOfficer = $this->user('discipline_officer', 'other-counter-officer@example.test');
        $student = $this->user('student', 'counter-student@example.test');
        $minor = $this->offense('MINOR-COUNTER', 'minor');
        $major = $this->offense('MAJOR-COUNTER', 'major');

        $this->caseWithOffense($student, $officer, $minor, 'resolved', 'DC-COUNTER-001');
        $this->caseWithOffense($student, $officer, $major, 'resolved', 'DC-COUNTER-002');
        $current = $this->caseWithOffense($student, $officer, $minor, 'open', 'DC-COUNTER-003');

        $this->actingAs($officer)
            ->getJson(route('officer.cases.offense-summary', $current))
            ->assertOk()
            ->assertJsonPath('resolved_minor_count', 1)
            ->assertJsonPath('active_minor_charge_count', 1)
            ->assertJsonPath('resolved_major_count', 1)
            ->assertJsonPath('same_offense_prior_count', 1)
            ->assertJsonPath('habitual_same_type_count', 2)
            ->assertJsonPath('habitual_different_type_count', 1)
            ->assertJsonPath('habitual_same_type_threshold', 3)
            ->assertJsonPath('habitual_different_type_threshold', 4);

        $this->get(route('officer.cases.show', $current))
            ->assertOk()
            ->assertSee('Offense Record Summary')
            ->assertSee('Active charges remain separate');

        (new CaseService)->resolveMinorCase($current, 'Counter refresh verification.', $officer->id);

        $this->getJson(route('officer.cases.offense-summary', $current))
            ->assertOk()
            ->assertJsonPath('resolved_minor_count', 2)
            ->assertJsonPath('active_minor_charge_count', 0)
            ->assertJsonPath('resolved_major_count', 1)
            ->assertJsonPath('same_offense_prior_count', 1)
            ->assertJsonPath('habitual_same_type_count', 2)
            ->assertJsonPath('habitual_different_type_count', 1);

        $this->actingAs($otherOfficer)
            ->getJson(route('officer.cases.offense-summary', $current))
            ->assertForbidden();
    }

    private function caseWithOffense(
        User $student,
        User $officer,
        OffenseCatalog $offense,
        string $status,
        string $caseNumber
    ): DisciplinaryCase {
        $case = DisciplinaryCase::create([
            'case_number' => $caseNumber,
            'student_id' => $student->id,
            'case_type' => $offense->offense_type,
            'case_type_locked' => false,
            'assigned_to' => $officer->id,
            'status' => $status,
            'confirmed_offense_id' => $offense->id,
            'prior_offense_count' => 0,
            'resolved_at' => $status === 'resolved' ? now() : null,
        ]);
        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'prior_offense_count' => 0,
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);

        return $case;
    }

    private function offense(string $code, string $type): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => "{$type} counter test offense",
            'description' => 'Used to verify live offense counters.',
            'keywords' => ['counter'],
            'created_at' => now(),
        ]);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Counter',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
