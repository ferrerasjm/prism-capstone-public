<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseResolution;
use App\Models\CounselingReferral;
use App\Models\DisciplinaryCase;
use App\Models\RiskScore;
use App\Models\Sanction;
use App\Models\User;
use App\Notifications\EvaluationMonitoringNotification;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EvaluationMonitoringPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_resolution_starts_the_monitoring_period_and_notifies_the_student(): void
    {
        Notification::fake();
        [$officer, $student, $case] = $this->context('DC-MONITOR-START');

        $caseService = app(CaseService::class);
        $caseService->initiateResolution($case, $officer->id);

        $caseService->recordResolution($case->fresh(), [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 8,
            'evaluation_monitoring_required' => true,
            'evaluation_monitoring_end_date' => today()->addMonth()->toDateString(),
        ], $officer->id);

        $sanction = Sanction::where('sanction_type', 'evaluation_monitoring')->firstOrFail();
        $resolution = $case->fresh()->resolution;

        // The period is dated at resolution time -- previously it was created with no due
        // date at all, so it could neither remind nor close itself.
        $this->assertSame('pending', $sanction->status);
        $this->assertSame(today()->addMonth()->toDateString(), $sanction->due_date->toDateString());
        Notification::assertNotSentTo($student, EvaluationMonitoringNotification::class);

        // ... and starts when the NFI formally notifies the student.
        $caseService->issueNFI($case->fresh(['resolution']), $resolution, $officer->id);

        $sanction->refresh();
        $this->assertSame('in_progress', $sanction->status);
        $this->assertNotNull($sanction->intervention_notification_sent_at);
        Notification::assertSentTo($student, EvaluationMonitoringNotification::class);
    }

    public function test_the_monitoring_end_date_is_required_when_monitoring_is_selected(): void
    {
        [$officer, , $case] = $this->context('DC-MONITOR-VALIDATION');
        app(CaseService::class)->initiateResolution($case, $officer->id);

        $this->actingAs($officer)
            ->post(route('officer.cases.resolution.store', $case), [
                'resolution_level' => 'sdo_level',
                'intervention_category' => 2,
                'us_hours_assigned' => 8,
                'evaluation_monitoring_required' => 1,
            ])
            ->assertSessionHasErrors('evaluation_monitoring_end_date');
    }

    public function test_a_clean_period_closes_itself_as_completed(): void
    {
        [$officer, , $case] = $this->context('DC-MONITOR-CLEAN');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $this->artisan('sanctions:close-monitoring-periods')->assertSuccessful();

        $sanction->refresh();
        $this->assertSame('completed', $sanction->status);
        $this->assertNotNull($sanction->completed_at);
        // "No further incidents" is an objective finding the record already supports,
        // so a clean period closes itself as Satisfactory.
        $this->assertSame('satisfactory', $sanction->assessment_outcome);
        $this->assertNull($sanction->monitoring_review_flagged_at);
    }

    public function test_a_period_with_new_cases_is_flagged_for_the_officer_rather_than_auto_marked_non_compliant(): void
    {
        [$officer, $student, $case] = $this->context('DC-MONITOR-DIRTY');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $newCase = DisciplinaryCase::create([
            'case_number' => 'DC-MONITOR-DIRTY-2',
            'student_id' => $student->id,
            'case_type' => 'minor',
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 1,
        ]);
        $newCase->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();

        $this->artisan('sanctions:close-monitoring-periods')->assertSuccessful();

        $sanction->refresh();
        // A registration hold must stay an officer decision, so the status is untouched.
        $this->assertSame('in_progress', $sanction->status);
        $this->assertNotNull($sanction->monitoring_review_flagged_at);

        $flaggedAt = $sanction->monitoring_review_flagged_at;
        $this->artisan('sanctions:close-monitoring-periods')->assertSuccessful();

        // Re-running must not re-flag or duplicate the audit entry.
        $this->assertEquals($flaggedAt, $sanction->fresh()->monitoring_review_flagged_at);
        $this->assertSame(1, AuditLog::where('module', 'sanctions')->where('record_id', $sanction->id)->count());
    }

    public function test_an_officer_assessment_clears_the_review_flag(): void
    {
        [$officer, , $case] = $this->context('DC-MONITOR-DECIDE');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);
        $sanction->update(['monitoring_review_flagged_at' => now()]);

        $this->actingAs($officer)
            ->patch(route('officer.sanctions.evaluation-monitoring.update', $sanction), [
                'due_date' => today()->toDateString(),
                'status' => 'completed',
                'assessment_outcome' => 'needs_further_intervention',
            ])
            ->assertSessionHasNoErrors();

        $sanction->refresh();
        $this->assertSame('completed', $sanction->status);
        $this->assertSame('needs_further_intervention', $sanction->assessment_outcome);
        $this->assertNull($sanction->monitoring_review_flagged_at);
    }

    /**
     * Category 2 is formative intervention and carries no escalation clause -- only
     * Category 1 probation does. A period that closes with an adverse finding must not
     * be able to mark the student non-compliant, because that blocks the lifting of a
     * registration hold.
     */
    public function test_an_evaluation_period_cannot_be_closed_as_non_compliant(): void
    {
        [$officer, , $case] = $this->context('DC-MONITOR-NOFAIL');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $this->actingAs($officer)
            ->patch(route('officer.sanctions.evaluation-monitoring.update', $sanction), [
                'due_date' => today()->toDateString(),
                'status' => 'non_compliant',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('in_progress', $sanction->fresh()->status);
    }

    public function test_closing_an_evaluation_period_requires_an_assessment(): void
    {
        [$officer, , $case] = $this->context('DC-MONITOR-NEEDS-FINDING');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $this->actingAs($officer)
            ->patch(route('officer.sanctions.evaluation-monitoring.update', $sanction), [
                'due_date' => today()->toDateString(),
                'status' => 'completed',
            ])
            ->assertSessionHasErrors('assessment_outcome');

        $this->assertSame('in_progress', $sanction->fresh()->status);
    }

    /**
     * The validator correctly rejects an incomplete submission, but a redirect-back with
     * $errors and no error UI on the page reads to an officer as "nothing happened, no
     * complaint either" -- indistinguishable from a silent success. The page must surface
     * the rejection.
     */
    public function test_the_sanction_page_displays_validation_errors(): void
    {
        [$officer, , $case] = $this->context('DC-MONITOR-ERROR-UI');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        // Establishes the "previous page" a failed submission redirects back to -- the
        // officer's actual sequence: view the page, then submit the form on it.
        $this->actingAs($officer)
            ->get(route('officer.sanctions.show', $sanction))
            ->assertOk();

        $response = $this->followingRedirects()->actingAs($officer)
            ->patch(route('officer.sanctions.evaluation-monitoring.update', $sanction), [
                'due_date' => today()->toDateString(),
                'status' => 'completed',
            ]);

        $response->assertOk();
        $response->assertSee('assessment outcome field is required', escape: false);
    }

    /**
     * A finding of "Needs Further Intervention" is the officer concluding the intervention
     * did not work. Without a consequence, that conclusion was recorded and forgotten.
     */
    public function test_an_adverse_finding_raises_a_counseling_referral(): void
    {
        [$officer, $student, $case] = $this->context('DC-MONITOR-ADVERSE');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $this->actingAs($officer)
            ->patch(route('officer.sanctions.evaluation-monitoring.update', $sanction), [
                'due_date' => today()->toDateString(),
                'status' => 'completed',
                'assessment_outcome' => 'needs_further_intervention',
            ])
            ->assertSessionHasNoErrors();

        $referral = CounselingReferral::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('pending', $referral->status);
        $this->assertNull($referral->guidance_officer_id);
        $this->assertSame('priority', $referral->urgency);

        $referralSanction = Sanction::where('sanction_type', 'counseling_referral')
            ->where('student_id', $student->id)
            ->firstOrFail();
        $this->assertSame($sanction->fresh()->case_resolution_id, $referralSanction->case_resolution_id);

        // The finding is now a scored risk factor, so it must recompute immediately.
        $this->assertGreaterThan(0, (float) RiskScore::where('student_id', $student->id)->value('composite_score'));
    }

    /**
     * The referral is a separate Sanction record with its own URL -- nothing else on the
     * evaluation sanction's own page pointed to it, so an officer who just raised one had
     * no way to find it again except searching the full sanctions list by hand.
     */
    public function test_the_raised_referral_is_visible_and_linked_from_the_evaluation_sanction_page(): void
    {
        [$officer, , $case] = $this->context('DC-MONITOR-VISIBLE');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $this->actingAs($officer)
            ->patch(route('officer.sanctions.evaluation-monitoring.update', $sanction), [
                'due_date' => today()->toDateString(),
                'status' => 'completed',
                'assessment_outcome' => 'needs_further_intervention',
            ])
            ->assertSessionHasNoErrors();

        $referralSanction = Sanction::where('sanction_type', 'counseling_referral')->firstOrFail();

        $response = $this->actingAs($officer)
            ->get(route('officer.sanctions.show', $sanction->fresh()))
            ->assertOk();

        $response->assertSee('Related Sanctions');
        $response->assertSee('Counseling Referral');
        $response->assertSee('No Guidance Officer covers this department');
        $response->assertSee(route('officer.sanctions.show', $referralSanction), escape: false);
    }

    /**
     * Amending notes or extending an already-adverse period must not open a second,
     * redundant referral.
     */
    public function test_resaving_the_same_adverse_finding_does_not_raise_a_second_referral(): void
    {
        [$officer, , $case] = $this->context('DC-MONITOR-ADVERSE-RESAVE');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $update = fn () => $this->actingAs($officer)->patch(
            route('officer.sanctions.evaluation-monitoring.update', $sanction),
            [
                'due_date' => today()->toDateString(),
                'status' => 'completed',
                'assessment_outcome' => 'needs_further_intervention',
                'notes' => 'Second save.',
            ]
        );

        $update()->assertSessionHasNoErrors();
        $update()->assertSessionHasNoErrors();

        $this->assertSame(1, Sanction::where('sanction_type', 'counseling_referral')->count());
    }

    /**
     * A clean auto-close records Satisfactory, which must not itself be read as
     * non-compliance by the risk model.
     */
    public function test_a_satisfactory_finding_does_not_count_toward_sanction_non_compliance(): void
    {
        [$officer, $student, $case] = $this->context('DC-MONITOR-SATISFACTORY');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $this->artisan('sanctions:close-monitoring-periods')->assertSuccessful();
        $this->assertSame('satisfactory', $sanction->fresh()->assessment_outcome);

        $score = app(\App\Services\RiskScoringService::class)->computeForStudent($student->id);
        $this->assertSame(0, $score->contributing_factors['non_compliant_sanctions']['raw_value']);
    }

    /**
     * A single reused "your period ends on X" message used to go out for every kind of
     * update -- extending the date, closing clean, or closing with an adverse finding --
     * which read as wrong once the period had actually already closed.
     */
    public function test_extending_the_period_sends_an_extended_notification(): void
    {
        Notification::fake();
        [$officer, $student, $case] = $this->context('DC-MONITOR-NOTIFY-EXTEND');
        $sanction = $this->monitoringSanctionDueOn($case, $officer, today()->addWeek());
        $newDueDate = today()->addWeeks(2);

        $this->actingAs($officer)
            ->patch(route('officer.sanctions.evaluation-monitoring.update', $sanction), [
                'due_date' => $newDueDate->toDateString(),
                'status' => 'in_progress',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $student,
            EvaluationMonitoringNotification::class,
            function (EvaluationMonitoringNotification $notification) use ($newDueDate) {
                $data = $notification->toDatabase($notification);
                return $data['kind'] === 'evaluation_monitoring_extended'
                    && str_contains($data['message'], $newDueDate->format('F d, Y'))
                    && ! str_contains($data['message'], 'completed');
            }
        );
    }

    public function test_closing_satisfactory_sends_a_closed_satisfactory_notification(): void
    {
        Notification::fake();
        [$officer, $student, $case] = $this->context('DC-MONITOR-NOTIFY-SAT');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $this->actingAs($officer)
            ->patch(route('officer.sanctions.evaluation-monitoring.update', $sanction), [
                'due_date' => today()->toDateString(),
                'status' => 'completed',
                'assessment_outcome' => 'satisfactory',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $student,
            EvaluationMonitoringNotification::class,
            function (EvaluationMonitoringNotification $notification) {
                $data = $notification->toDatabase($notification);
                return $data['kind'] === 'evaluation_monitoring_closed'
                    && str_contains($data['message'], 'Satisfactory')
                    && ! str_contains($data['message'], 'Needs Further Intervention');
            }
        );
    }

    public function test_closing_with_an_adverse_finding_sends_a_notification_mentioning_the_referral(): void
    {
        Notification::fake();
        [$officer, $student, $case] = $this->context('DC-MONITOR-NOTIFY-NEEDS');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $this->actingAs($officer)
            ->patch(route('officer.sanctions.evaluation-monitoring.update', $sanction), [
                'due_date' => today()->toDateString(),
                'status' => 'completed',
                'assessment_outcome' => 'needs_further_intervention',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $student,
            EvaluationMonitoringNotification::class,
            function (EvaluationMonitoringNotification $notification) {
                $data = $notification->toDatabase($notification);
                return $data['kind'] === 'evaluation_monitoring_closed'
                    && str_contains($data['message'], 'Needs Further Intervention')
                    && str_contains($data['message'], 'Guidance Services Office');
            }
        );
    }

    /**
     * An auto-close (the scheduler, not an officer) previously sent nothing at all --
     * the student only found out by revisiting their sanctions page.
     */
    public function test_an_automatic_clean_close_notifies_the_student(): void
    {
        Notification::fake();
        [$officer, $student, $case] = $this->context('DC-MONITOR-NOTIFY-AUTO');
        $this->monitoringSanction($case, $officer, endedDaysAgo: 2);

        $this->artisan('sanctions:close-monitoring-periods')->assertSuccessful();

        Notification::assertSentTo(
            $student,
            EvaluationMonitoringNotification::class,
            function (EvaluationMonitoringNotification $notification) {
                $data = $notification->toDatabase($notification);
                return $data['kind'] === 'evaluation_monitoring_closed'
                    && str_contains($data['message'], 'Satisfactory');
            }
        );
    }

    public function test_a_case_opened_mid_period_is_surfaced_on_the_officer_sanction_page(): void
    {
        [$officer, $student, $case] = $this->context('DC-MONITOR-LIVE');
        $sanction = $this->monitoringSanctionDueOn($case, $officer, today()->addWeek());
        $this->caseOpenedDaysAgo($student, $officer, 'DC-MONITOR-LIVE-2', 3);

        $response = $this->actingAs($officer)
            ->get(route('officer.sanctions.show', $sanction))
            ->assertOk();

        // Before this notice the case landed silently in the list and the officer had no
        // signal until the scheduler flagged the period on its end date.
        $response->assertSee('1 new case');
        $response->assertSee("won't auto-close", escape: false);
        $response->assertSee('DC-MONITOR-LIVE-2');
        $response->assertDontSee('holding the period open');
    }

    public function test_the_mid_period_notice_gives_way_to_the_end_of_period_flag(): void
    {
        [$officer, $student, $case] = $this->context('DC-MONITOR-HANDOFF');
        $sanction = $this->monitoringSanction($case, $officer, endedDaysAgo: 2);
        $this->caseOpenedDaysAgo($student, $officer, 'DC-MONITOR-HANDOFF-2', 5);

        $this->artisan('sanctions:close-monitoring-periods')->assertSuccessful();

        $response = $this->actingAs($officer)
            ->get(route('officer.sanctions.show', $sanction->fresh()))
            ->assertOk();

        // Only one of the two notices may show: the flag is the one that asks for a decision.
        $response->assertSee('holding the period open');
        $response->assertDontSee("won't auto-close", escape: false);
    }

    public function test_a_closed_period_reports_its_cases_without_asking_for_a_decision(): void
    {
        [$officer, $student, $case] = $this->context('DC-MONITOR-CLOSED');
        $sanction = $this->monitoringSanctionDueOn($case, $officer, today()->addWeek());
        $this->caseOpenedDaysAgo($student, $officer, 'DC-MONITOR-CLOSED-2', 3);
        $sanction->update(['status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($officer)
            ->get(route('officer.sanctions.show', $sanction))
            ->assertOk();

        $response->assertSee('DC-MONITOR-CLOSED-2');
        $response->assertDontSee("won't auto-close", escape: false);
    }

    public function test_a_long_case_list_scrolls_inside_the_card(): void
    {
        [$officer, $student, $case] = $this->context('DC-MONITOR-MANY');
        $sanction = $this->monitoringSanctionDueOn($case, $officer, today()->addWeek());

        foreach (range(1, 5) as $index) {
            $this->caseOpenedDaysAgo($student, $officer, "DC-MONITOR-MANY-{$index}", $index);
        }

        $response = $this->actingAs($officer)
            ->get(route('officer.sanctions.show', $sanction))
            ->assertOk();

        $response->assertSee('5 new cases');
        $response->assertSee('class="tracking-scroll"', escape: false);
    }

    public function test_a_short_case_list_is_not_boxed_into_a_scroller(): void
    {
        [$officer, $student, $case] = $this->context('DC-MONITOR-FEW');
        $sanction = $this->monitoringSanctionDueOn($case, $officer, today()->addWeek());

        foreach (range(1, 4) as $index) {
            $this->caseOpenedDaysAgo($student, $officer, "DC-MONITOR-FEW-{$index}", $index);
        }

        $this->actingAs($officer)
            ->get(route('officer.sanctions.show', $sanction))
            ->assertOk()
            ->assertDontSee('class="tracking-scroll"', escape: false);
    }

    private function caseOpenedDaysAgo(User $student, User $officer, string $caseNumber, int $daysAgo): DisciplinaryCase
    {
        $case = DisciplinaryCase::create([
            'case_number' => $caseNumber,
            'student_id' => $student->id,
            'case_type' => 'minor',
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 1,
        ]);

        $case->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();

        return $case->fresh();
    }

    private function monitoringSanction(DisciplinaryCase $case, User $officer, int $endedDaysAgo): Sanction
    {
        return $this->monitoringSanctionDueOn($case, $officer, today()->subDays($endedDaysAgo));
    }

    private function monitoringSanctionDueOn(DisciplinaryCase $case, User $officer, Carbon $dueDate): Sanction
    {
        $resolution = CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'evaluation_monitoring_required' => true,
            'resolved_by' => $officer->id,
            'resolved_at' => now()->subMonth(),
            'appeal_deadline' => now()->subWeeks(3),
            'appeal_filed' => false,
            'created_at' => now()->subMonth(),
        ]);

        $sanction = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $case->student_id,
            'sanction_type' => 'evaluation_monitoring',
            'status' => 'in_progress',
            'due_date' => $dueDate->toDateString(),
        ]);

        $sanction->forceFill(['created_at' => now()->subMonth()])->saveQuietly();

        return $sanction->fresh();
    }

    /**
     * @return array{0: User, 1: User, 2: DisciplinaryCase}
     */
    private function context(string $caseNumber): array
    {
        $officer = $this->user('discipline_officer', "officer-{$caseNumber}@example.test");
        $student = $this->user('student', "student-{$caseNumber}@example.test");

        $case = DisciplinaryCase::create([
            'case_number' => $caseNumber,
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'under_review',
            'prior_offense_count' => 0,
            'assigned_sanction_category' => 2,
        ]);

        return [$officer, $student, $case];
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
