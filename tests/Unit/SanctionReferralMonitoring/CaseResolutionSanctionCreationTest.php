<?php

declare(strict_types=1);

namespace Tests\Unit\SanctionReferralMonitoring;

use App\Models\CounselingReferral;
use App\Models\DisciplinaryCase;
use App\Models\RiskFactorWeight;
use App\Models\Sanction;
use App\Models\User;
use App\Services\CaseService;
use App\Services\MajorOffenseWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Module 5 - Sanction and Referral Monitoring
 * Component: Case Resolution and Sanction Record Creation
 *            (App\Services\CaseService::recordResolution)
 * Test Cycle No. 1 - 5 test cases.
 *
 * Recording a resolution is where the intervention category is turned into
 * the concrete obligations the student must complete.
 */
class CaseResolutionSanctionCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seedRiskWeights();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Scenario 1 - Normal: a Category 1 resolution creates a probation obligation. */
    public function test_a_category_1_resolution_creates_a_probation_obligation(): void
    {
        [$case, $officer] = $this->caseAwaitingResolution('DC-2026-7401');

        $resolution = (new CaseService)->recordResolution($case, [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 1,
            'probation_terms' => 2,
            'probation_end_date' => '2026-12-15',
        ], $officer->id);

        $sanctions = Sanction::where('case_resolution_id', $resolution->id)->get();

        $this->assertCount(1, $sanctions);
        $this->assertSame('probation', $sanctions->first()->sanction_type);
        $this->assertSame('in_progress', $sanctions->first()->status);
        $this->assertSame('2026-12-15', $sanctions->first()->due_date->toDateString());
    }

    /** Scenario 2 - Normal: a Category 2 resolution creates every selected obligation. */
    public function test_a_category_2_resolution_creates_every_selected_obligation(): void
    {
        [$case, $officer] = $this->caseAwaitingResolution('DC-2026-7402');

        $resolution = (new CaseService)->recordResolution($case, [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 24,
            'counseling_referred' => true,
            'evaluation_monitoring_required' => true,
            'evaluation_monitoring_end_date' => today()->addMonth()->toDateString(),
        ], $officer->id);

        $types = Sanction::where('case_resolution_id', $resolution->id)
            ->pluck('sanction_type')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['counseling_referral', 'evaluation_monitoring', 'university_service'],
            $types
        );
    }

    /** Scenario 3 - Boundary: a counseling obligation also opens a pending referral. */
    public function test_a_counseling_obligation_also_opens_a_pending_referral(): void
    {
        [$case, $officer] = $this->caseAwaitingResolution('DC-2026-7403');

        $resolution = (new CaseService)->recordResolution($case, [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 12,
            'counseling_referred' => true,
        ], $officer->id);

        $sanction = Sanction::where('case_resolution_id', $resolution->id)
            ->where('sanction_type', 'counseling_referral')
            ->firstOrFail();
        $referral = CounselingReferral::where('sanction_id', $sanction->id)->firstOrFail();

        $this->assertSame('pending', $referral->status);
        $this->assertNull($referral->guidance_officer_id);
        $this->assertSame($officer->id, $referral->referred_by);
    }

    /**
     * Scenario 3a - Normal: a counseling referral is assigned automatically at resolution
     * time rather than left for an officer to route separately from the Sanctions screen.
     */
    public function test_a_referral_is_auto_assigned_to_the_sole_active_guidance_officer(): void
    {
        [$case, $officer] = $this->caseAwaitingResolution('DC-2026-7404');

        $guidance = User::create([
            'first_name' => 'Maria',
            'last_name' => 'Andaya',
            'email' => 'auto-assign.guidance@nu-clark.edu.ph',
            'password' => Hash::make('Password123!'),
            'role' => 'guidance_officer',
            'status' => 'active',
        ]);

        $resolution = (new CaseService)->recordResolution($case, [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 12,
            'counseling_referred' => true,
        ], $officer->id);

        $sanction = Sanction::where('case_resolution_id', $resolution->id)
            ->where('sanction_type', 'counseling_referral')
            ->firstOrFail();
        $referral = CounselingReferral::where('sanction_id', $sanction->id)->firstOrFail();

        $this->assertSame($guidance->id, $referral->guidance_officer_id);
        $this->assertSame('pending', $referral->status);
    }

    /**
     * Scenario 3b - Boundary: when more than one Guidance Officer is eligible, the tie
     * breaks toward whoever currently carries fewer pending referrals, so coverage load
     * spreads out instead of always landing on the same person.
     */
    public function test_auto_assignment_prefers_the_officer_with_fewer_pending_referrals(): void
    {
        [$case, $officer] = $this->caseAwaitingResolution('DC-2026-7405');

        $busy = User::create([
            'first_name' => 'Busy',
            'last_name' => 'Officer',
            'email' => 'busy.guidance@nu-clark.edu.ph',
            'password' => Hash::make('Password123!'),
            'role' => 'guidance_officer',
            'status' => 'active',
        ]);
        $free = User::create([
            'first_name' => 'Free',
            'last_name' => 'Officer',
            'email' => 'free.guidance@nu-clark.edu.ph',
            'password' => Hash::make('Password123!'),
            'role' => 'guidance_officer',
            'status' => 'active',
        ]);

        // An existing pending referral makes $busy the less attractive pick.
        $otherSanction = Sanction::create([
            'student_id' => $case->student_id,
            'sanction_type' => 'counseling_referral',
            'status' => 'pending',
        ]);
        CounselingReferral::create([
            'sanction_id' => $otherSanction->id,
            'student_id' => $case->student_id,
            'guidance_officer_id' => $busy->id,
            'referred_by' => $officer->id,
            'referred_at' => now(),
            'urgency' => 'routine',
            'status' => 'pending',
        ]);

        $resolution = (new CaseService)->recordResolution($case, [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 12,
            'counseling_referred' => true,
        ], $officer->id);

        $sanction = Sanction::where('case_resolution_id', $resolution->id)
            ->where('sanction_type', 'counseling_referral')
            ->firstOrFail();
        $referral = CounselingReferral::where('sanction_id', $sanction->id)->firstOrFail();

        $this->assertSame($free->id, $referral->guidance_officer_id);
    }

    /** Scenario 4 - Boundary: the severe categories each create their single terminal sanction. */
    public function test_the_severe_categories_each_create_their_terminal_sanction(): void
    {
        $expected = [3 => 'non_readmission', 4 => 'exclusion', 5 => 'expulsion'];

        foreach ($expected as $category => $sanctionType) {
            [$case, $officer] = $this->caseAwaitingResolution('DC-2026-750'.$category);

            $resolution = (new CaseService)->recordResolution($case, [
                'resolution_level' => 'upcc',
                'intervention_category' => $category,
            ], $officer->id);

            $sanctions = Sanction::where('case_resolution_id', $resolution->id)->get();

            $this->assertCount(1, $sanctions, "Category {$category} should create one sanction.");
            $this->assertSame($sanctionType, $sanctions->first()->sanction_type);
            $this->assertSame('completed', $sanctions->first()->status);
        }
    }

    /** Scenario 5 - Normal: resolving closes the case and opens the five-working-day appeal window. */
    public function test_resolving_closes_the_case_and_opens_the_appeal_window(): void
    {
        Carbon::setTestNow('2026-08-03 10:00:00');
        [$case, $officer] = $this->caseAwaitingResolution('DC-2026-7406');

        $resolution = (new CaseService)->recordResolution($case, [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 1,
            'probation_terms' => 1,
            'probation_end_date' => '2026-11-30',
        ], $officer->id);

        $this->assertSame('resolved', $case->fresh()->status);
        $this->assertSame($officer->id, $case->fresh()->resolved_by);
        $this->assertNotNull($case->fresh()->resolved_at);
        $this->assertSame('2026-08-10', $resolution->appeal_deadline->toDateString());
        $this->assertSame(
            MajorOffenseWorkflow::appealDeadline(Carbon::parse('2026-08-03 10:00:00'))->toDateString(),
            $resolution->appeal_deadline->toDateString()
        );
        $this->assertFalse($resolution->appeal_filed);
    }

    /** @return array{0: DisciplinaryCase, 1: User} */
    private function caseAwaitingResolution(string $number): array
    {
        static $sequence = 0;
        $sequence++;

        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Resolved'.$sequence,
            'email' => "resolution.student{$sequence}@students.nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Resolving'.$sequence,
            'email' => "resolution.officer{$sequence}@nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => $number,
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'pending_resolution',
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
        ]);

        return [$case, $officer];
    }

    private function seedRiskWeights(): void
    {
        RiskFactorWeight::query()->delete();

        foreach (config('risk_scoring.factors') as $code => $factor) {
            RiskFactorWeight::create([
                'factor_code' => $code,
                'factor_name' => $factor['name'],
                'factor_type' => $factor['type'],
                'count_cap' => $factor['cap'],
                'weight' => $factor['weight'],
                'description' => $factor['description'],
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }
    }
}
