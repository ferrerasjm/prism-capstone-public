<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\RiskScoreSnapshot;
use App\Models\User;
use App\Services\RiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RiskScoringHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_recomputation_records_an_explainable_before_and_after_snapshot(): void
    {
        $student = $this->user('student', 'risk-history@students.nu-clark.edu.ph');
        $officer = $this->user('discipline_officer', 'risk-history-officer@nu-clark.edu.ph');
        $service = app(RiskScoringService::class);

        $service->computeForStudent($student->id, 'manual_recompute', $officer->id);

        DisciplinaryCase::create([
            'case_number' => 'RISK-HISTORY-001',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);

        $score = $service->computeForStudent($student->id, 'manual_recompute', $officer->id);
        $snapshots = RiskScoreSnapshot::where('student_id', $student->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $snapshots);
        $this->assertNull($snapshots->first()->previous_composite_score);
        $this->assertSame('0.00', $snapshots->last()->previous_composite_score);
        $this->assertSame('manual_recompute', $snapshots->last()->trigger);
        $this->assertSame($officer->id, $snapshots->last()->triggered_by);
        $this->assertSame(config('risk_scoring.methodology_version'), $snapshots->last()->methodology_version);
        $this->assertArrayHasKey('unresolved_cases', $snapshots->last()->contributing_factors);
        $this->assertSame('unresolved_cases', $snapshots->last()->factor_changes[0]['factor_code']);
        $this->assertGreaterThan(0, $snapshots->last()->factor_changes[0]['delta']);
        $this->assertSame('increasing', $score->trend);
        $this->assertGreaterThan(0, $snapshots->last()->scoreDelta());
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Risk',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
