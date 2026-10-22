<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CounselingReferral;
use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The Guidance Office asked for two things a referral used to arrive without: why the
 * student was actually referred (previously a hardcoded "Assigned as part of Category X
 * disciplinary intervention." string, regardless of the student), and how soon they're
 * expected to see them.
 */
class CounselingReferralUrgencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_must_supply_a_reason_and_urgency_to_refer_for_counseling(): void
    {
        [$officer, $case] = $this->pendingResolutionCase(category: 2);

        $this->actingAs($officer)
            ->post(route('officer.cases.resolution.store', $case), [
                'intervention_category' => 2,
                'counseling_referred' => '1',
                // referral_reason and urgency deliberately omitted
            ])
            ->assertSessionHasErrors(['referral_reason', 'urgency']);

        $this->assertDatabaseMissing('counseling_referrals', ['student_id' => $case->student_id]);
    }

    public function test_officer_referral_reason_and_urgency_reach_the_guidance_officer(): void
    {
        [$officer, $case] = $this->pendingResolutionCase(category: 2);
        $guidance = $this->user('guidance_officer');

        $this->actingAs($officer)
            ->post(route('officer.cases.resolution.store', $case), [
                'intervention_category' => 2,
                'counseling_referred' => '1',
                'referral_reason' => 'Repeated classroom disruptions; possible undiagnosed test anxiety worth exploring.',
                'urgency' => 'immediate',
                'guidance_officer_id' => $guidance->id,
            ])
            ->assertSessionHasNoErrors();

        $referral = CounselingReferral::where('student_id', $case->student_id)->firstOrFail();
        $this->assertSame(
            'Repeated classroom disruptions; possible undiagnosed test anxiety worth exploring.',
            $referral->referral_reason
        );
        $this->assertSame('immediate', $referral->urgency);

        $this->actingAs($guidance)
            ->get(route('guidance.referrals.show', $referral))
            ->assertOk()
            ->assertSee('Repeated classroom disruptions; possible undiagnosed test anxiety worth exploring.')
            ->assertSee('Immediate')
            ->assertSee('2–3 school days');
    }

    public function test_a_referral_created_without_urgency_defaults_to_routine(): void
    {
        [, $case] = $this->pendingResolutionCase(category: 2);

        $referral = CounselingReferral::create([
            'student_id' => $case->student_id,
            'referred_at' => now(),
            'referral_reason' => 'Administrative referral requirement.',
            'status' => 'pending',
        ]);

        $this->assertSame('routine', $referral->fresh()->urgency);
        $this->assertSame('Non-Urgent', $referral->fresh()->urgencyDetails()['label']);
    }

    public function test_guidance_officer_can_filter_and_see_urgency_on_the_referral_list(): void
    {
        $guidance = $this->user('guidance_officer');
        $officer = $this->user('discipline_officer');
        $students = [];

        foreach (['routine', 'priority', 'immediate'] as $urgency) {
            $student = User::create([
                'last_name' => ucfirst($urgency),
                'first_name' => 'Student',
                'email' => fake()->unique()->safeEmail(),
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
            ]);
            $students[$urgency] = $student;

            CounselingReferral::create([
                'student_id' => $student->id,
                'guidance_officer_id' => $guidance->id,
                'referred_by' => $officer->id,
                'referred_at' => now(),
                'referral_reason' => "A {$urgency} referral.",
                'urgency' => $urgency,
                'status' => 'pending',
            ]);
        }

        $this->actingAs($guidance)
            ->get(route('guidance.referrals.index'))
            ->assertOk()
            ->assertSee('Immediate')
            ->assertSee('Priority')
            ->assertSee('Non-Urgent')
            ->assertSeeInOrder([
                $students['immediate']->last_name,
                $students['priority']->last_name,
                $students['routine']->last_name,
            ]);

        // The urgency filter dropdown always lists every level as an <option>, so
        // "Non-Urgent" and "Priority" appear on the page regardless of what's filtered in —
        // check for the student's row instead of the bare urgency word.
        $this->actingAs($guidance)
            ->get(route('guidance.referrals.index', ['urgency' => 'immediate']))
            ->assertOk()
            ->assertSee($students['immediate']->last_name.', '.$students['immediate']->first_name)
            ->assertDontSee($students['routine']->last_name.', '.$students['routine']->first_name)
            ->assertDontSee($students['priority']->last_name.', '.$students['priority']->first_name);
    }

    /** @return array{0: User, 1: DisciplinaryCase} */
    private function pendingResolutionCase(int $category): array
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');

        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-URGENCY-TEST',
            'offense_type' => 'major',
            'title' => 'Test Offense',
            'description' => 'Test offense.',
            'keywords' => ['test'],
            'created_at' => now(),
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-URGENCY-'.fake()->unique()->numerify('#####'),
            'case_type' => 'major',
            'case_type_locked' => true,
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'status' => 'pending_resolution',
            'confirmed_offense_id' => $offense->id,
            'assigned_sanction_category' => $category,
            'prior_offense_count' => 0,
        ]);

        return [$officer, $case];
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst(str_replace('_', ' ', $role)),
            'first_name' => 'Test',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
