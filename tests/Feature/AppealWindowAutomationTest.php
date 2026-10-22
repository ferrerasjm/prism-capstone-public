<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DisciplinaryCase;
use App\Models\UpccReferral;
use App\Models\User;
use App\Notifications\UpccDecisionNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppealWindowAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_expired_appeal_window_without_appeal_is_finalized_automatically(): void
    {
        Carbon::setTestNow('2026-07-12 06:30:00');
        [$student, $case, $referral] = $this->distributedUpccCase('2026-07-11');

        $this->artisan('cases:finalize-expired-appeals')
            ->expectsOutput('Finalized 1 expired UPCC appeal window(s).')
            ->assertSuccessful();

        $this->assertSame('pending_resolution', $case->fresh()->status);
        $this->assertNotNull($referral->fresh()->decision_final_at);

        $notification = $student->notifications()
            ->where('type', UpccDecisionNotification::class)
            ->firstOrFail();

        $this->assertSame('upcc_finalized', $notification->data['kind']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'action' => 'update',
            'module' => 'cases',
            'record_id' => $case->id,
            'reason' => 'Appeal window expired without a filed appeal; finalized automatically by scheduler.',
        ]);
    }

    public function test_appeal_window_remains_open_through_deadline_date(): void
    {
        Carbon::setTestNow('2026-07-12 23:59:00');
        [$student, $case, $referral] = $this->distributedUpccCase('2026-07-12');

        $this->artisan('cases:finalize-expired-appeals')
            ->expectsOutput('Finalized 0 expired UPCC appeal window(s).')
            ->assertSuccessful();

        $this->assertSame('pending_upcc', $case->fresh()->status);
        $this->assertNull($referral->fresh()->decision_final_at);
        $this->assertSame(0, $student->notifications()->count());
    }

    public function test_pending_appeals_are_not_auto_finalized(): void
    {
        Carbon::setTestNow('2026-07-12 06:30:00');
        [$student, $case, $referral] = $this->distributedUpccCase('2026-07-01', true);

        $this->artisan('cases:finalize-expired-appeals')
            ->expectsOutput('Finalized 0 expired UPCC appeal window(s).')
            ->assertSuccessful();

        $this->assertSame('pending_upcc', $case->fresh()->status);
        $this->assertNull($referral->fresh()->decision_final_at);
        $this->assertSame(0, $student->notifications()->count());
        $this->assertFalse(AuditLog::where('record_id', $case->id)->exists());
    }

    private function distributedUpccCase(string $appealWindowEnd, bool $appealFiled = false): array
    {
        $student = $this->user('student', fake()->unique()->safeEmail());
        $coordinator = $this->user('discipline_coordinator', fake()->unique()->safeEmail());

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-APPEAL-'.fake()->unique()->numerify('#####'),
            'student_id' => $student->id,
            'case_type' => 'major',
            'case_type_locked' => true,
            'status' => 'pending_upcc',
            'prior_offense_count' => 0,
        ]);

        $referral = UpccReferral::create([
            'case_id' => $case->id,
            'referred_by' => $coordinator->id,
            'referred_at' => now()->subDays(10),
            'scheduled_conference_at' => now()->subDays(8),
            'conference_location' => 'Conference Room',
            'student_attended' => true,
            'status' => $appealFiled ? 'reconsideration_filed' : 'concluded',
            'decision_summary' => 'UPCC decision summary for automated finalization test.',
            'decision_issued_at' => now()->subDays(7),
            'presidents_council_action' => 'approved',
            'presidents_council_acted_at' => now(),
            'decision_distributed_at' => now()->subDays(6),
            'appeal_window_start' => now()->subDays(6)->toDateString(),
            'appeal_window_end' => $appealWindowEnd,
            'appeal_filed' => $appealFiled,
            'appeal_notes' => $appealFiled ? 'Student filed an appeal for reconsideration.' : null,
            'proceedings_type' => 'full_hearing',
        ]);

        return [$student, $case, $referral];
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Appeal',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
