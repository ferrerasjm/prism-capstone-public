<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IncidentReport;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Services\CaseService;
use App\Services\IncidentReportService;
use Database\Seeders\RiskFactorWeightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IncidentCaseClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_converted_case_remains_unclassified_until_offense_is_confirmed(): void
    {
        $officer = $this->user('discipline_officer', 'officer@example.test');
        $student = $this->user('student', 'student@example.test');

        $report = IncidentReport::create([
            'report_number' => 'IR-2026-TEST',
            'source_type' => 'violation_slip',
            'reported_by_user_id' => $officer->id,
            'student_id' => $student->id,
            'date_of_incident' => now()->toDateString(),
            'description' => 'A report awaiting formal offense classification.',
            'violation_type' => 'Others',
            'status' => 'under_review',
        ]);

        $case = (new IncidentReportService)->convertToCase($report, $officer)[0];

        $this->assertNull($case->case_type);
        $this->assertFalse($case->case_type_locked);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $student->id,
        ]);

        $this->actingAs($student)
            ->get(route('student.cases.show', $case))
            ->assertOk()
            ->assertSee('Initial Review')
            ->assertSee('Incident and Allegation')
            ->assertSee('A report awaiting formal offense classification.');

        $minorOffense = OffenseCatalog::create([
            'code' => 'MINOR-TEST',
            'offense_type' => 'minor',
            'title' => 'Minor Test Offense',
            'description' => 'Test offense.',
            'keywords' => ['test'],
            'created_at' => now(),
        ]);

        (new CaseService)->confirmOffense($case, $minorOffense->id, $officer->id);

        $this->assertSame('minor', $case->fresh()->case_type);

        $secondMinorOffense = OffenseCatalog::create([
            'code' => 'MINOR-TEST-2',
            'offense_type' => 'minor',
            'title' => 'Second Minor Test Offense',
            'description' => 'Second test offense.',
            'keywords' => ['second'],
            'created_at' => now(),
        ]);

        (new CaseService)->confirmOffense($case->fresh(), $secondMinorOffense->id, $officer->id);

        $this->assertSame(2, $case->caseOffenses()->where('status', 'confirmed')->count());
        $this->assertSame('minor', $case->fresh()->case_type);

        $this->seed(RiskFactorWeightSeeder::class);
        (new CaseService)->resolveMinorCase($case->fresh(), 'Minor case resolution test.', $officer->id);

        $this->assertSame('resolved', $case->fresh()->status);
        $this->assertDatabaseHas('risk_scores', ['student_id' => $student->id]);

        $this->actingAs($student)
            ->getJson(route('student.cases.status', $case))
            ->assertOk()
            ->assertJsonPath('status', 'resolved');

        $this->get(route('student.cases.show', $case))
            ->assertOk()
            ->assertSee('Resolved');
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
