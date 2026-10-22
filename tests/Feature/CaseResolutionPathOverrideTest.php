<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\OffenseCatalog;
use App\Models\SanctionMatrix;
use App\Models\User;
use App\Services\CaseService;
use App\Services\IncidentReportService;
use App\Services\SanctionRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Resolving directly is only an override when the system pointed at a UPCC
 * referral, so the reason is demanded on that path and not on the recommended
 * one — the same rule the classification and sanction overrides already follow.
 */
class CaseResolutionPathOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolving_against_a_upcc_recommendation_requires_an_override_reason(): void
    {
        [$case, $officer] = $this->caseUnderReview('full_denial');
        $this->assertSame('upcc_referral', $case->path_recommendation);

        $this->actingAs($officer)
            ->post(route('officer.cases.initiate-resolution', $case))
            ->assertSessionHasErrors('override_reason');

        $case = $case->fresh();
        $this->assertSame('under_review', $case->status, 'A rejected override must not advance the case.');
        $this->assertNull($case->path_override_reason);
    }

    public function test_a_too_short_override_reason_is_rejected(): void
    {
        [$case, $officer] = $this->caseUnderReview('full_denial');

        $this->actingAs($officer)
            ->post(route('officer.cases.initiate-resolution', $case), ['override_reason' => 'Too short.'])
            ->assertSessionHasErrors('override_reason');

        $this->assertSame('under_review', $case->fresh()->status);
    }

    public function test_a_upcc_recommendation_can_be_overridden_with_a_reason(): void
    {
        [$case, $officer] = $this->caseUnderReview('full_denial');
        $reason = 'The denial concerns a factual detail that does not affect the sanction, so SDO level is sufficient.';

        $this->actingAs($officer)
            ->post(route('officer.cases.initiate-resolution', $case), ['override_reason' => $reason])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel');

        $case = $case->fresh();
        $this->assertSame('pending_resolution', $case->status);
        $this->assertSame($reason, $case->path_override_reason);
    }

    public function test_the_recommended_path_needs_no_reason_and_records_none(): void
    {
        [$case, $officer] = $this->caseUnderReview('full_admission');
        $this->assertSame('direct_resolution', $case->path_recommendation);

        $this->actingAs($officer)
            ->post(route('officer.cases.initiate-resolution', $case))
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel');

        $case = $case->fresh();
        $this->assertSame('pending_resolution', $case->status);
        $this->assertNull($case->path_override_reason, 'Following the recommendation is not an override.');
    }

    /**
     * A reason volunteered on the recommended path is not an override either, so
     * it is dropped rather than filed as one.
     */
    public function test_a_reason_sent_on_the_recommended_path_is_not_stored_as_an_override(): void
    {
        [$case, $officer] = $this->caseUnderReview('full_admission');

        $this->actingAs($officer)
            ->post(route('officer.cases.initiate-resolution', $case), [
                'override_reason' => 'A reason supplied even though this path was the one recommended.',
            ])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel');

        $this->assertNull($case->fresh()->path_override_reason);
    }

    /** @return array{0: DisciplinaryCase, 1: User} */
    public function test_the_officer_case_page_shows_the_evaluation_outcome_as_a_coloured_pill(): void
    {
        [$case, $officer] = $this->caseUnderReview('full_denial');

        $this->actingAs($officer)
            ->get(route('officer.cases.show', $case))
            ->assertOk()
            ->assertSee('Full Denial')
            ->assertSee('prism-badge prism-badge-red', false);
    }

    private function caseUnderReview(string $admissionType): array
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $caseService = new CaseService;

        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-PATH-'.strtoupper(uniqid()),
            'offense_type' => 'major',
            'title' => 'Major offense used for path override coverage.',
            'description' => 'Offense used for path override coverage.',
            'keywords' => ['override'],
            'created_at' => now(),
        ]);
        SanctionMatrix::create(['offense_catalog_id' => $offense->id, 'offense_count' => 1, 'sanction_category' => 2]);

        $report = IncidentReport::create([
            'report_number' => 'IR-PATH-'.uniqid(),
            'source_type' => 'direct_encoding',
            'reported_by_user_id' => $officer->id,
            'student_id' => $student->id,
            'date_of_incident' => today(),
            'description' => 'Report used to exercise the resolution path override.',
            'status' => 'under_review',
        ]);

        $case = (new IncidentReportService)->convertToCase($report, $officer)[0];
        $caseService->confirmOffense($case->fresh(), $offense->id, $officer->id);
        $caseService->confirmSanction($case->fresh(), 2, $officer->id);
        $caseService->issueNTE($case->fresh(), ['incident_date' => today()->toDateString()], $officer->id);

        $this->actingAs($student)
            ->post(route('student.cases.explanation.store', $case->fresh()), [
                'explanation_text' => 'This is my written account of the incident, submitted for the officer to review.',
            ]);

        $case = $case->fresh(['explanation']);
        $caseService->saveEvaluation(
            $case,
            $case->explanation,
            $admissionType,
            null,
            $officer->id,
            new SanctionRecommendationService
        );

        return [$case->fresh(), $officer];
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst(str_replace('_', ' ', $role)),
            'first_name' => 'Path',
            'email' => 'path-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
