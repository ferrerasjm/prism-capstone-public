<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseOffense;
use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\RiskFactorWeight;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\Sanction;
use App\Models\User;
use App\Services\RiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RiskScoringMethodologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_installs_six_documented_factors_totaling_one_hundred_percent(): void
    {
        $weights = RiskFactorWeight::where('is_active', true)->get();

        $this->assertCount(6, $weights);
        $this->assertEqualsCanonicalizing(
            array_keys(config('risk_scoring.factors')),
            $weights->pluck('factor_code')->all()
        );
        $this->assertEqualsWithDelta(1.0, $weights->sum(fn ($weight) => (float) $weight->weight), 0.0001);
    }

    public function test_score_uses_only_documented_explainable_factors(): void
    {
        $student = $this->user('student', 'risk-student@students.nu-clark.edu.ph');
        $officer = $this->user('discipline_officer', 'risk-officer@nu-clark.edu.ph');
        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-TEST',
            'offense_type' => 'major',
            'title' => 'Risk methodology test offense',
            'description' => 'Used only to verify the documented risk methodology.',
            'keywords' => ['risk'],
            'created_at' => now(),
        ]);

        $resolvedCase = DisciplinaryCase::create([
            'case_number' => 'RISK-RESOLVED-001',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'major',
            'status' => 'resolved',
            'resolved_at' => now(),
            'escalation_flagged' => true,
            'prior_offense_count' => 0,
        ]);
        CaseOffense::create([
            'case_id' => $resolvedCase->id,
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);
        DisciplinaryCase::create([
            'case_number' => 'RISK-ACTIVE-001',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 1,
        ]);
        Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'university_service',
            'status' => 'non_compliant',
        ]);

        $score = app(RiskScoringService::class)->computeForStudent($student->id);

        $this->assertSame('50.00', $score->composite_score);
        $this->assertSame('moderate', $score->risk_band);
        $this->assertEqualsCanonicalizing(
            array_keys(config('risk_scoring.factors')),
            array_keys($score->contributing_factors)
        );
        $this->assertNotEmpty($score->contributing_factors['total_offense_count']['description']);
    }

    public function test_admin_must_update_the_complete_model_with_weights_totaling_one_hundred_percent(): void
    {
        $admin = $this->user('super_admin', 'risk-demo-user-001@nu-clark.edu.ph');
        $weights = RiskFactorWeight::where('is_active', true)->get();
        $payload = $this->payload($weights);
        $firstId = (string) $weights->first()->id;
        $payload[$firstId]['weight'] = 0.50;

        $this->actingAs($admin)
            ->from(route('admin.risk-weights.index'))
            ->patch(route('admin.risk-weights.update'), ['weights' => $payload])
            ->assertRedirect(route('admin.risk-weights.index'))
            ->assertSessionHas('error', 'Active risk-factor weights must total exactly 100.00%.');

        $this->assertEqualsWithDelta(
            1.0,
            RiskFactorWeight::where('is_active', true)->get()->sum(fn ($weight) => (float) $weight->weight),
            0.0001
        );
    }

    public function test_balanced_weight_update_is_atomic_and_audited(): void
    {
        $admin = $this->user('super_admin', 'risk-audit-demo-user-001@nu-clark.edu.ph');
        $student = $this->user('student', 'risk-weight-student@students.nu-clark.edu.ph');
        DisciplinaryCase::create([
            'case_number' => 'RISK-WEIGHT-ACTIVE-001',
            'student_id' => $student->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);
        app(RiskScoringService::class)->computeForStudent($student->id, 'case_created');

        $weights = RiskFactorWeight::where('is_active', true)->get();
        $payload = $this->payload($weights);
        $totalFactor = $weights->firstWhere('factor_code', 'total_offense_count');
        $unresolvedFactor = $weights->firstWhere('factor_code', 'unresolved_cases');
        $payload[(string) $totalFactor->id]['weight'] = 0.15;
        $payload[(string) $unresolvedFactor->id]['weight'] = 0.20;

        $this->actingAs($admin)
            ->patch(route('admin.risk-weights.update'), ['weights' => $payload])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('risk_factor_weights', [
            'factor_code' => 'total_offense_count',
            'weight' => 0.15,
        ]);
        $this->assertTrue(AuditLog::where('module', 'risk_factor_weights')->exists());
        $this->assertSame(
            '6.67',
            RiskScore::where('student_id', $student->id)->value('composite_score')
        );

        $snapshot = RiskScoreSnapshot::where('student_id', $student->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('risk_weights_updated', $snapshot->trigger);
        $this->assertSame($admin->id, $snapshot->triggered_by);
        $this->assertSame('Risk weights updated', $snapshot->triggerLabel());
    }

    public function test_admin_can_create_update_archive_and_restore_a_risk_factor(): void
    {
        $admin = $this->user('super_admin', 'risk-lifecycle-demo-user-001@nu-clark.edu.ph');
        RiskFactorWeight::where('factor_code', 'unresolved_cases')->firstOrFail()->delete();

        $this->actingAs($admin)->post(route('admin.risk-weights.store'), [
            'factor_code' => 'unresolved_cases',
            'factor_name' => 'Open Case Load',
            'rule' => ['source' => 'open_cases', 'case_type' => 'any', 'months' => null, 'threshold' => 1],
            'factor_type' => 'count',
            'weight_percent' => 10,
            'count_cap' => 4,
            'description' => 'Active disciplinary cases that have not reached a final resolution.',
        ])->assertRedirect()->assertSessionHas('success');

        $factor = RiskFactorWeight::where('factor_name', 'Open Case Load')->firstOrFail();
        $this->assertTrue($factor->is_active);
        $this->assertSame('0.1000', $factor->weight);

        $this->actingAs($admin)->patch(route('admin.risk-weights.factor.update', $factor), [
            'factor_name' => 'Pending Case Load',
            'rule' => ['source' => 'open_cases', 'case_type' => 'major', 'months' => 6, 'threshold' => 1],
            'factor_type' => 'count',
            'weight_percent' => 12.5,
            'count_cap' => 5,
            'description' => 'All active disciplinary cases awaiting a final resolution.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('risk_factor_weights', [
            'id' => $factor->id, 'factor_name' => 'Pending Case Load', 'count_cap' => 5, 'weight' => 0.125,
        ]);
        $this->assertEqualsWithDelta(1.0, RiskFactorWeight::where('is_active', true)->sum('weight'), 0.0001);

        $this->actingAs($admin)->get(route('admin.risk-weights.index'))
            ->assertOk()
            ->assertSee('Weight Percentage')
            ->assertSee('Select Records to Monitor')
            ->assertSee('Select a Severity')
            ->assertSee('Select a Type')
            ->assertDontSee('name="weights[', false);

        $this->actingAs($admin)->patch(route('admin.risk-weights.toggle-status', $factor), [
            'archive_reason' => 'The panel approved temporarily excluding this measurement.',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($factor->fresh()->is_active);
        $this->assertEqualsWithDelta(1.0, RiskFactorWeight::where('is_active', true)->sum('weight'), 0.0001);

        $this->actingAs($admin)->patch(route('admin.risk-weights.toggle-status', $factor))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($factor->fresh()->is_active);
        $this->assertSame('0.0000', $factor->fresh()->weight);
        $this->assertGreaterThanOrEqual(4, AuditLog::where('module', 'risk_factor_weights')->count());
    }

    public function test_archiving_requires_a_reason_and_the_last_active_factor_cannot_be_archived(): void
    {
        $admin = $this->user('super_admin', 'risk-archive-demo-user-001@nu-clark.edu.ph');
        $factor = RiskFactorWeight::where('is_active', true)->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.risk-weights.toggle-status', $factor))
            ->assertSessionHasErrors('archive_reason');
        $this->assertTrue($factor->fresh()->is_active);

        RiskFactorWeight::where('id', '!=', $factor->id)->update(['is_active' => false, 'weight' => 0]);
        $factor->update(['weight' => 1]);
        $this->actingAs($admin)->patch(route('admin.risk-weights.toggle-status', $factor), [
            'archive_reason' => 'Attempting to remove the final configured measurement.',
        ])->assertSessionHas('error', 'At least one active risk factor is required.');
        $this->assertTrue($factor->fresh()->is_active);
    }

    public function test_custom_rule_is_evaluated_recalculated_and_preserved_in_history(): void
    {
        $admin = $this->user('super_admin', 'builder-demo-user-001@nu-clark.edu.ph');
        $student = $this->user('student', 'builder-student@students.nu-clark.edu.ph');
        $other = $this->user('student', 'builder-other@students.nu-clark.edu.ph');
        $this->travelTo(now()->startOfDay());
        foreach ([
            [$student->id, 'major', now()->subMonth()],
            [$student->id, 'major', now()->subMonths(6)->subDay()],
            [$student->id, 'minor', now()->subMonth()],
            [$other->id, 'major', now()->subMonth()],
        ] as $index => [$studentId, $severity, $date]) {
            DisciplinaryCase::create([
                'case_number' => 'BUILDER-'.$index, 'student_id' => $studentId,
                'status' => 'open', 'case_type' => $severity, 'prior_offense_count' => 0,
            ])->forceFill(['created_at' => $date])->save();
        }
        $rule = ['source' => 'open_cases', 'case_type' => 'major', 'months' => 6, 'threshold' => 2];
        $payload = ['factor_name' => 'Repeated recent major cases', 'factor_type' => 'boolean',
            'weight_percent' => 0, 'description' => 'Two open major cases created within six months.', 'rule' => $rule];
        $this->actingAs($admin)->post(route('admin.risk-weights.store'), $payload)
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $factor = RiskFactorWeight::where('factor_name', $payload['factor_name'])->firstOrFail();
        $weights = RiskFactorWeight::where('is_active', true)->get();
        $points = $this->payload($weights);
        foreach ($points as $id => &$values) {
            $values['weight'] = (int) $id === $factor->id ? 1 : 0;
        }
        unset($values);
        $this->patch(route('admin.risk-weights.update'), ['weights' => $points])->assertSessionHas('success');
        $score = RiskScore::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('0.00', $score->composite_score);
        $this->assertFalse($score->contributing_factors[$factor->factor_code]['triggered']);

        $payload['rule']['threshold'] = 1;
        $payload['weight_percent'] = 100;
        $this->patch(route('admin.risk-weights.factor.update', $factor), $payload)->assertSessionHas('success');
        $this->assertSame('100.00', $score->fresh()->composite_score);
        $this->assertSame(1, $score->fresh()->contributing_factors[$factor->factor_code]['rule']['threshold']);
        $this->assertTrue(RiskScoreSnapshot::where('student_id', $student->id)->get()->contains(
            fn ($snapshot) => ($snapshot->contributing_factors[$factor->factor_code]['rule']['threshold'] ?? null) === 2
        ));
        $this->get(route('admin.risk-weights.index'))->assertOk()->assertSee($payload['factor_name']);
        $this->travel(7)->months();
        $service = app(RiskScoringService::class);
        $service->computeForAllStudents();
        $this->assertSame('0.00', $score->fresh()->composite_score);
    }

    public function test_rule_builder_rejects_unknown_sources_and_invalid_conditions(): void
    {
        $admin = $this->user('super_admin', 'invalid-rule-demo-user-001@nu-clark.edu.ph');
        $payload = ['factor_name' => 'Invalid rule', 'factor_type' => 'boolean', 'weight_percent' => 5, 'description' => 'Invalid rule test'];
        foreach ([
            ['source' => 'attendance', 'case_type' => 'any', 'threshold' => 1],
            ['source' => 'open_cases', 'case_type' => 'any', 'threshold' => 0],
            ['source' => 'open_cases', 'case_type' => 'any', 'threshold' => 1, 'months' => -1],
            ['source' => 'non_compliant_sanctions', 'case_type' => 'major', 'threshold' => 1],
            ['source' => 'open_cases', 'case_type' => 'any', 'threshold' => 1, 'sql' => '1=1'],
        ] as $rule) {
            $this->actingAs($admin)->post(route('admin.risk-weights.store'), $payload + ['rule' => $rule])
                ->assertSessionHasErrors();
        }
        $this->assertDatabaseMissing('risk_factor_weights', ['factor_name' => 'Invalid rule']);
    }

    public function test_non_admin_cannot_change_rules(): void
    {
        $officer = $this->user('discipline_officer', 'rules-officer@nu-clark.edu.ph');
        $factor = RiskFactorWeight::firstOrFail();
        $this->actingAs($officer)->post(route('admin.risk-weights.store'), [])->assertForbidden();
        $this->patch(route('admin.risk-weights.factor.update', $factor), [])->assertForbidden();
        $this->patch(route('admin.risk-weights.toggle-status', $factor), [])->assertForbidden();
    }

    private function payload($weights): array
    {
        return $weights->mapWithKeys(fn (RiskFactorWeight $weight) => [
            (string) $weight->id => [
                'weight' => (float) $weight->weight,
                'count_cap' => $weight->count_cap,
            ],
        ])->all();
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
