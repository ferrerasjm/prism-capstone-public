<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\CounselingReferral;
use App\Models\Sanction;
use App\Models\StudentMonitoringFlag;
use App\Models\User;
use App\Notifications\GuidanceReferralStatusNotification;
use App\Services\GuidanceReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuidanceReferralWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_cannot_be_completed_before_acknowledgment(): void
    {
        [, , $guidance, $referral] = $this->referral();
        $referral->update(['guidance_officer_id' => $guidance->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Acknowledge the referral');

        (new GuidanceReferralService)->markCompleted($referral, $guidance->id);
    }

    public function test_completion_atomically_updates_sanction_and_notifies_student(): void
    {
        [, $student, $guidance, $referral] = $this->referral();
        $referral->update(['guidance_officer_id' => $guidance->id]);
        $service = new GuidanceReferralService;
        $service->acknowledge($referral->fresh(), $guidance->id);
        $service->markCompleted($referral->fresh(), $guidance->id);

        $this->assertDatabaseHas('counseling_referrals', [
            'id' => $referral->id,
            'status' => 'completed',
            'confirmed_by' => $guidance->id,
        ]);
        $this->assertDatabaseHas('sanctions', [
            'id' => $referral->sanction_id,
            'status' => 'completed',
            'verified_by' => $guidance->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => GuidanceReferralStatusNotification::class,
            'notifiable_id' => $student->id,
        ]);
    }

    public function test_incomplete_status_updates_sanction_without_storing_reason_in_sanction_notes(): void
    {
        [, , $guidance, $referral] = $this->referral();
        $referral->update([
            'guidance_officer_id' => $guidance->id,
            'acknowledged_at' => now(),
        ]);

        (new GuidanceReferralService)->markIncomplete(
            $referral->fresh(),
            'Student did not complete the required referral coordination.',
            $guidance->id,
        );

        $this->assertSame('incomplete', $referral->fresh()->status);
        $this->assertSame('non_compliant', $referral->sanction->fresh()->status);
        $this->assertNull($referral->sanction->fresh()->notes);
    }

    public function test_second_consecutive_incomplete_referral_on_same_case_raises_registration_hold(): void
    {
        [$officer, $student, $guidance, $caseResolution] = $this->caseResolution();
        $service = new GuidanceReferralService;

        $first = $this->referralForCase($caseResolution, $student, $officer, $guidance);
        $service->markIncomplete($first->fresh(), 'Missed both scheduled sessions.', $guidance->id);

        $this->assertDatabaseMissing('student_monitoring_flags', [
            'student_id' => $student->id,
            'flag_type' => 'registration_hold',
            'is_active' => true,
        ]);

        $second = $this->referralForCase($caseResolution, $student, $officer, $guidance);
        $service->markIncomplete($second->fresh(), 'Missed both scheduled sessions again.', $guidance->id);

        $this->assertDatabaseHas('student_monitoring_flags', [
            'student_id' => $student->id,
            'flag_type' => 'registration_hold',
            'is_active' => true,
        ]);
    }

    public function test_completing_a_referral_after_a_hold_was_raised_lifts_it(): void
    {
        [$officer, $student, $guidance, $caseResolution] = $this->caseResolution();
        $service = new GuidanceReferralService;

        $first = $this->referralForCase($caseResolution, $student, $officer, $guidance);
        $service->markIncomplete($first->fresh(), 'Missed both scheduled sessions.', $guidance->id);
        $second = $this->referralForCase($caseResolution, $student, $officer, $guidance);
        $service->markIncomplete($second->fresh(), 'Missed both scheduled sessions again.', $guidance->id);

        $this->assertDatabaseHas('student_monitoring_flags', [
            'student_id' => $student->id,
            'flag_type' => 'registration_hold',
            'is_active' => true,
        ]);

        $third = $this->referralForCase($caseResolution, $student, $officer, $guidance);
        $service->markCompleted($third->fresh(), $guidance->id);

        $this->assertDatabaseHas('student_monitoring_flags', [
            'student_id' => $student->id,
            'flag_type' => 'registration_hold',
            'is_active' => false,
        ]);
    }

    private function referral(): array
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $guidance = $this->user('guidance_officer');
        $sanction = Sanction::create([
            'student_id' => $student->id,
            'sanction_type' => 'counseling_referral',
            'status' => 'pending',
        ]);
        $referral = CounselingReferral::create([
            'sanction_id' => $sanction->id,
            'student_id' => $student->id,
            'referred_by' => $officer->id,
            'referred_at' => now(),
            'referral_reason' => 'Administrative referral requirement.',
            'status' => 'pending',
        ]);

        return [$officer, $student, $guidance, $referral];
    }

    private function caseResolution(): array
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $guidance = $this->user('guidance_officer');
        $caseResolution = CaseResolution::create([
            'resolution_level' => 'sdo_level',
            'intervention_category' => 1,
            'counseling_referred' => true,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => now()->addDays(5),
            'created_at' => now(),
        ]);

        return [$officer, $student, $guidance, $caseResolution];
    }

    private function referralForCase(
        CaseResolution $caseResolution,
        User $student,
        User $officer,
        User $guidance
    ): CounselingReferral {
        $sanction = Sanction::create([
            'case_resolution_id' => $caseResolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'counseling_referral',
            'status' => 'pending',
        ]);

        $referral = CounselingReferral::create([
            'sanction_id' => $sanction->id,
            'student_id' => $student->id,
            'guidance_officer_id' => $guidance->id,
            'referred_by' => $officer->id,
            'referred_at' => now(),
            'referral_reason' => 'Administrative referral requirement.',
            'status' => 'pending',
            'acknowledged_at' => now(),
        ]);

        return $referral;
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Referral',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
