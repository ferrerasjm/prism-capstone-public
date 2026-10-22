<?php

declare(strict_types=1);

namespace Tests\Unit\SanctionReferralMonitoring;

use App\Models\CounselingReferral;
use App\Models\Sanction;
use App\Models\User;
use App\Services\GuidanceReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Module 5 - Sanction and Referral Monitoring
 * Component: Guidance Referral Workflow Guards (App\Services\GuidanceReferralService)
 * Test Cycle No. 1 - 3 test cases.
 *
 * Assignment itself is no longer a manual action to guard -- every referral is now
 * routed automatically by department (see GuidanceReferralService::matchOfficerForStudent()),
 * so what remains here is the acknowledgment/completion workflow that follows.
 */
class GuidanceReferralWorkflowGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /** Scenario 3 - Failure: only the assigned Guidance Officer may acknowledge a referral. */
    public function test_only_the_assigned_guidance_officer_may_acknowledge_a_referral(): void
    {
        $assigned = $this->guidanceOfficer('active');
        $other = $this->guidanceOfficer('active', 'second');
        [$referral] = $this->referral($assigned);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only the assigned Guidance Officer can acknowledge this referral.');

        (new GuidanceReferralService)->acknowledge($referral, $other->id);
    }

    /** Scenario 4 - Failure: completion is blocked until the referral is acknowledged. */
    public function test_completion_is_blocked_until_the_referral_is_acknowledged(): void
    {
        $guidance = $this->guidanceOfficer('active');
        [$referral] = $this->referral($guidance);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Acknowledge the referral before updating its completion status.');

        (new GuidanceReferralService)->markCompleted($referral, $guidance->id);
    }

    /** Scenario 5 - Normal: an acknowledged referral completes and settles its sanction. */
    public function test_an_acknowledged_referral_completes_and_settles_its_sanction(): void
    {
        $guidance = $this->guidanceOfficer('active');
        [$referral, $sanction] = $this->referral($guidance);
        $service = new GuidanceReferralService;

        $service->acknowledge($referral, $guidance->id);
        $service->markCompleted($referral->fresh(), $guidance->id);

        $this->assertSame('completed', $referral->fresh()->status);
        $this->assertSame($guidance->id, $referral->fresh()->confirmed_by);
        $this->assertSame('completed', $sanction->fresh()->status);
        $this->assertNotNull($sanction->fresh()->completed_at);
    }

    /** @return array{0: CounselingReferral, 1: Sanction} */
    private function referral(?User $guidanceOfficer = null): array
    {
        $student = $this->student();

        $sanction = Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'counseling_referral',
            'status' => 'pending',
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        $referral = CounselingReferral::create([
            'sanction_id' => $sanction->id,
            'student_id' => $student->id,
            'guidance_officer_id' => $guidanceOfficer?->id,
            'referred_by' => null,
            'referred_at' => now(),
            'referral_reason' => 'Counseling referral recorded during case resolution.',
            'status' => 'pending',
        ]);

        return [$referral, $sanction];
    }

    private function student(): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create([
            'last_name' => 'Villanueva',
            'first_name' => 'Referred'.$sequence,
            'email' => "referral.student{$sequence}@students.nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);
    }

    private function guidanceOfficer(string $status, string $suffix = 'first'): User
    {
        return User::create([
            'last_name' => 'Gonzales',
            'first_name' => 'Guidance',
            'email' => "guidance.{$suffix}.{$status}@nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'guidance_officer',
            'status' => $status,
        ]);
    }

}
