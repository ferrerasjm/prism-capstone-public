<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseOffense;
use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinePolicySetting;
use App\Models\OffenseCatalog;
use App\Models\OffenseCategory;
use App\Models\SanctionMatrix;
use App\Models\User;
use App\Services\EscalationService;
use App\Services\SanctionRecommendationService;
use Database\Seeders\OffenseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OffensePolicyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_a_major_offense_and_activate_it_after_a_recommendation(): void
    {
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->get(route('admin.offense-policy.index'))
            ->assertOk()->assertSee('Offense Catalog')->assertSee('Minor Escalation Threshold');

        $this->post(route('admin.offense-policy.offenses.store'), $this->offenseInput())
            ->assertSessionHas('success');
        $offense = OffenseCatalog::where('code', 'MAJOR-TEST')->firstOrFail();
        $this->assertFalse($offense->is_active);
        $this->get(route('admin.offense-policy.index', ['offense' => $offense->id, 'status' => 'all']))
            ->assertOk()->assertSee('Test policy offense')->assertSee('Add Recommendation');
        $this->patch(route('admin.offense-policy.offenses.toggle', $offense), [
            'reason' => 'Attempt to activate without a first-offense recommendation.',
        ])->assertSessionHasErrors('offense');

        $this->post(route('admin.offense-policy.recommendations.save', $offense), [
            'offense_count' => 1,
            'sanction_category' => 2,
            'us_hours_min' => 40,
            'us_hours_max' => 40,
            'reason' => 'Approved handbook recommendation for testing.',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('sanction_matrix', [
            'offense_catalog_id' => $offense->id,
            'offense_count' => 1,
            'sanction_category' => 2,
            'us_hours_recommended' => 40,
        ]);

        $this->patch(route('admin.offense-policy.offenses.toggle', $offense), [
            'reason' => 'Approved for the current handbook edition.',
        ])->assertSessionHas('success');
        $this->assertTrue($offense->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['module' => 'offense_catalog', 'action' => 'update']);
        $matrix = SanctionMatrix::where('offense_catalog_id', $offense->id)->firstOrFail();
        $this->delete(route('admin.offense-policy.recommendations.delete', [$offense, $matrix]), [
            'reason' => 'Attempt to remove the only first-offense rule.',
        ])->assertSessionHasErrors('recommendation');
    }

    public function test_policy_tabs_show_the_selected_section_and_threshold_errors_reopen_the_modal(): void
    {
        $admin = $this->user('super_admin');
        $matrixPage = $this->actingAs($admin)->get(route('admin.offense-policy.index', ['tab' => 'matrix']))
            ->assertOk()
            ->assertSee('id="sanction-matrix"', false)
            ->assertSee('Sanction Matrix</a>', false);
        $this->assertMatchesRegularExpression('/id="offense-catalog"\s+hidden/', $matrixPage->getContent());

        $this->from(route('admin.offense-policy.index', ['tab' => 'catalog']))
            ->patch(route('admin.offense-policy.thresholds.update'), [
                '_form' => 'threshold', 'same_type_minor_threshold' => 1,
                'different_type_minor_threshold' => 4,
                'reason' => 'Testing validation navigation.',
            ])->assertSessionHasErrors('same_type_minor_threshold');
        $thresholdPage = $this->get(route('admin.offense-policy.index', ['tab' => 'catalog']))
            ->assertOk()
            ->assertSee('id="thresholdModal"', false)
            ->assertSee('data-auto-open="true"', false);
        $this->assertDoesNotMatchRegularExpression('/id="offense-catalog"\s+hidden/', $thresholdPage->getContent());
    }

    public function test_sanction_matrix_page_size_matches_the_pagination_control(): void
    {
        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-PAGE', 'offense_type' => 'major', 'title' => 'Pagination test offense',
            'description' => 'Offense for testing sanction matrix pagination.', 'keywords' => [],
            'is_active' => true, 'created_at' => now(),
        ]);
        foreach (range(1, 11) as $count) {
            SanctionMatrix::create([
                'offense_catalog_id' => $offense->id,
                'offense_count' => $count,
                'sanction_category' => 1,
            ]);
        }

        $defaultPage = $this->actingAs($this->user('super_admin'))
            ->get(route('admin.offense-policy.index', ['tab' => 'matrix']))
            ->assertOk()->assertSee('10 / page');
        $this->assertMatchesRegularExpression('/Showing\s*<strong>1<\/strong>\s*to\s*<strong>10<\/strong>\s*of\s*<strong>11<\/strong> recommendations/', $defaultPage->getContent());

        $expandedPage = $this->get(route('admin.offense-policy.index', ['tab' => 'matrix', 'per_page' => 25]))
            ->assertOk()->assertSee('25 / page');
        $this->assertMatchesRegularExpression('/Showing\s*<strong>1<\/strong>\s*to\s*<strong>11<\/strong>\s*of\s*<strong>11<\/strong> recommendations/', $expandedPage->getContent());
    }

    public function test_archived_offenses_remain_on_cases_but_cannot_be_selected_again(): void
    {
        $admin = $this->user('super_admin');
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $offense = OffenseCatalog::create([
            'code' => 'MINOR-TEST', 'offense_type' => 'minor',
            'title' => 'Historical test offense', 'description' => 'Historical offense for an existing case.',
            'keywords' => ['historical'], 'is_active' => true, 'created_at' => now(),
        ]);
        $case = DisciplinaryCase::create([
            'case_number' => 'POLICY-CASE-1', 'student_id' => $student->id,
            'assigned_to' => $officer->id, 'status' => 'open', 'case_type' => 'minor',
            'prior_offense_count' => 0,
        ]);
        CaseOffense::create([
            'case_id' => $case->id, 'offense_catalog_id' => $offense->id, 'status' => 'confirmed',
        ]);

        $this->actingAs($admin)->patch(route('admin.offense-policy.offenses.toggle', $offense), [
            'reason' => 'Replaced by the new handbook offense.',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('case_offenses', ['case_id' => $case->id, 'offense_catalog_id' => $offense->id]);
        $this->assertFalse($offense->fresh()->is_active);

        $this->patch(route('admin.offense-policy.offenses.update', $offense), $this->offenseInput([
            'code' => 'MINOR-TEST', 'offense_type' => 'minor',
        ]))->assertSessionHasErrors('offense');

        $this->actingAs($officer)->get(route('officer.cases.offense-search', ['q' => 'Historical test offense']))
            ->assertOk()->assertJsonMissing(['id' => $offense->id]);
    }

    public function test_thresholds_are_used_by_the_escalation_service_and_are_admin_only(): void
    {
        $student = $this->user('student');
        $this->actingAs($student)->get(route('admin.offense-policy.index'))->assertForbidden();
        $coordinator = $this->user('discipline_coordinator');
        $this->actingAs($coordinator)->patch(route('admin.offense-policy.thresholds.update'), [
            'same_type_minor_threshold' => 2,
            'different_type_minor_threshold' => 2,
            'reason' => 'Unauthorized coordinator policy change.',
        ])->assertForbidden();

        $admin = $this->user('super_admin');
        $this->actingAs($admin)->patch(route('admin.offense-policy.thresholds.update'), [
            'same_type_minor_threshold' => 5,
            'different_type_minor_threshold' => 6,
            'reason' => 'Updated after the approved policy review.',
        ])->assertSessionHas('success');

        $progress = app(EscalationService::class)->studentProgress($student->id);
        $this->assertSame(5, $progress['same_type_threshold']);
        $this->assertSame(6, $progress['different_type_threshold']);
        $this->assertSame('Student Handbook: habitual disregard', DisciplinePolicySetting::current()->policy_reference);
        $this->assertDatabaseHas('audit_logs', ['module' => 'discipline_policy_settings', 'action' => 'update']);
    }

    public function test_reseeding_does_not_replace_an_edited_catalog(): void
    {
        $this->seed(OffenseCatalogSeeder::class);
        OffenseCatalog::where('code', 'MINOR-01')->update(['title' => 'Approved revised title']);
        $this->seed(OffenseCatalogSeeder::class);
        $this->assertSame('Approved revised title', OffenseCatalog::where('code', 'MINOR-01')->value('title'));
    }

    public function test_missing_repeat_count_uses_the_nearest_earlier_recommendation(): void
    {
        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-GAP', 'offense_type' => 'major', 'title' => 'Gap test offense',
            'description' => 'Offense for checking repeat count fallback.', 'keywords' => [],
            'is_active' => true, 'created_at' => now(),
        ]);
        SanctionMatrix::create(['offense_catalog_id' => $offense->id, 'offense_count' => 1, 'sanction_category' => 2]);
        SanctionMatrix::create(['offense_catalog_id' => $offense->id, 'offense_count' => 3, 'sanction_category' => 4]);

        $this->assertSame(2, app(SanctionRecommendationService::class)->recommend($offense->id, 1)['sanction_category']);
    }

    public function test_category_and_recommendation_archive_preserve_records_and_stop_new_suggestions(): void
    {
        $this->actingAs($this->user('super_admin'));
        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-ARCHIVE', 'offense_type' => 'major', 'title' => 'Archive policy test',
            'description' => 'An offense for testing recommendation archiving.', 'keywords' => [],
            'is_active' => false, 'created_at' => now(),
        ]);

        $this->post(route('admin.offense-policy.recommendations.save', $offense), [
            'offense_count' => 1, 'sanction_category' => 2,
            'us_hours_min' => 12, 'us_hours_max' => 16,
            'reason' => 'Approved recommendation for archive testing.',
        ])->assertSessionHas('success');
        $matrix = SanctionMatrix::where('offense_catalog_id', $offense->id)->firstOrFail();
        $this->assertSame(2, app(SanctionRecommendationService::class)->recommend($offense->id, 0)['sanction_category']);
        $this->post(route('admin.offense-policy.recommendations.save', $offense), [
            '_is_new' => '1', 'offense_count' => 1, 'sanction_category' => 1,
            'reason' => 'Duplicate recommendation should not overwrite policy.',
        ])->assertSessionHasErrors('offense_count');
        $this->assertSame(2, $matrix->fresh()->sanction_category);
        $this->get(route('admin.offense-policy.index', ['tab' => 'matrix']))
            ->assertOk()->assertSee('Sanction Matrix</a>', false)->assertSee('MAJOR-ARCHIVE');

        $this->patch(route('admin.offense-policy.categories.archive', 2), [
            'reason' => 'Testing category archive while in use.',
        ])->assertSessionHasErrors('category');
        $this->patch(route('admin.offense-policy.recommendations.archive', [$offense, $matrix]), [
            'reason' => 'Recommendation superseded by new policy.',
        ])->assertSessionHas('success');
        $this->assertFalse($matrix->fresh()->is_active);
        $this->assertNull(app(SanctionRecommendationService::class)->recommend($offense->id, 0)['sanction_category']);

        $this->patch(route('admin.offense-policy.categories.archive', 2), [
            'reason' => 'Category archived for the policy test.',
        ])->assertSessionHas('success');
        $this->assertFalse(OffenseCategory::findOrFail(2)->is_active);
        $this->patch(route('admin.offense-policy.categories.update', 2), [
            'number' => 2, 'intervention' => 'Updated Formative Intervention',
            'reason' => 'Restoring the approved intervention category.',
        ])->assertSessionHas('success');
        $this->assertTrue(OffenseCategory::findOrFail(2)->is_active);
        $this->assertSame('Category 2 — Updated Formative Intervention',
            \App\Models\CaseResolution::interventionLabel(2));
    }

    public function test_officer_uses_the_current_matrix_for_an_unassigned_case(): void
    {
        $admin = $this->user('super_admin');
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-LIVE', 'offense_type' => 'major', 'title' => 'Live policy offense',
            'description' => 'Offense used for live policy recommendation tests.',
            'keywords' => [], 'is_active' => true, 'created_at' => now(),
        ]);
        $case = DisciplinaryCase::create([
            'case_number' => 'POLICY-LIVE-1', 'student_id' => $student->id,
            'assigned_to' => $officer->id, 'status' => 'open', 'case_type' => 'major',
            'confirmed_offense_id' => $offense->id, 'prior_offense_count' => 0,
            'suggested_sanction_category' => 2,
        ]);
        CaseOffense::create([
            'case_id' => $case->id, 'offense_catalog_id' => $offense->id,
            'status' => 'confirmed', 'prior_offense_count' => 0,
        ]);
        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id, 'offense_count' => 1,
            'sanction_category' => 2, 'us_hours_min' => 10, 'us_hours_max' => 20,
        ]);

        $this->actingAs($admin)->post(route('admin.offense-policy.recommendations.save', $offense), [
            'offense_count' => 1, 'sanction_category' => 3,
            'reason' => 'Approved updated policy for current cases.',
        ])->assertSessionHas('success');

        $this->actingAs($officer)->get(route('officer.cases.show', $case))
            ->assertOk()->assertSee('Category 3 — Non-Readmission');
        $this->post(route('officer.cases.confirm-sanction', $case), [
            'sanction_category' => 3,
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $case->fresh()->assigned_sanction_category);
    }

    public function test_category_intervention_edits_appear_on_student_resolution(): void
    {
        $admin = $this->user('super_admin');
        $student = $this->user('student');
        $officer = $this->user('discipline_officer');
        $case = DisciplinaryCase::create([
            'case_number' => 'POLICY-STUDENT-1', 'student_id' => $student->id,
            'assigned_to' => $officer->id, 'status' => 'resolved', 'case_type' => 'major',
            'prior_offense_count' => 0,
        ]);
        CaseResolution::create([
            'case_id' => $case->id, 'resolution_level' => 'sdo_level',
            'intervention_category' => 3, 'resolved_by' => $officer->id,
            'resolved_at' => now(), 'appeal_deadline' => now()->addDays(5),
            'appeal_filed' => false, 'created_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.offense-policy.categories.update', 3), [
            'number' => 3, 'intervention' => 'Revised Non-Readmission',
            'description' => 'The approved revised intervention description.',
            'reason' => 'Approved policy wording for student decisions.',
        ])->assertSessionHas('success');

        $this->actingAs($student)->get(route('student.cases.show', $case))
            ->assertOk()->assertSee('Category 3 — Revised Non-Readmission')
            ->assertSee('The approved revised intervention description.');
    }

    public function test_archiving_a_repeat_recommendation_updates_the_officer_choice(): void
    {
        $admin = $this->user('super_admin');
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-REPEAT', 'offense_type' => 'major', 'title' => 'Repeat policy offense',
            'description' => 'Offense used for repeat recommendation tests.',
            'keywords' => [], 'is_active' => true, 'created_at' => now(),
        ]);
        $priorCase = DisciplinaryCase::create([
            'case_number' => 'POLICY-PRIOR-1', 'student_id' => $student->id,
            'assigned_to' => $officer->id, 'status' => 'resolved', 'case_type' => 'major',
            'prior_offense_count' => 0,
        ]);
        CaseOffense::create(['case_id' => $priorCase->id, 'offense_catalog_id' => $offense->id, 'status' => 'confirmed']);
        $case = DisciplinaryCase::create([
            'case_number' => 'POLICY-REPEAT-2', 'student_id' => $student->id,
            'assigned_to' => $officer->id, 'status' => 'open', 'case_type' => 'major',
            'confirmed_offense_id' => $offense->id, 'prior_offense_count' => 1,
            'suggested_sanction_category' => 3,
        ]);
        CaseOffense::create(['case_id' => $case->id, 'offense_catalog_id' => $offense->id, 'status' => 'confirmed', 'prior_offense_count' => 1]);
        SanctionMatrix::create(['offense_catalog_id' => $offense->id, 'offense_count' => 1, 'sanction_category' => 2]);
        $repeat = SanctionMatrix::create(['offense_catalog_id' => $offense->id, 'offense_count' => 2, 'sanction_category' => 3]);

        $this->actingAs($admin)->patch(route('admin.offense-policy.recommendations.archive', [$offense, $repeat]), [
            'reason' => 'Repeat recommendation replaced by earlier rule.',
        ])->assertSessionHas('success');
        $this->assertSame(2, app(SanctionRecommendationService::class)->recommend($offense->id, 1)['sanction_category']);

        $this->actingAs($officer)->post(route('officer.cases.confirm-sanction', $case), [
            'sanction_category' => 2,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $case->fresh()->assigned_sanction_category);
    }

    public function test_archived_category_cannot_be_assigned_until_restored(): void
    {
        $admin = $this->user('super_admin');
        $officer = $this->user('discipline_officer');
        $case = DisciplinaryCase::create([
            'case_number' => 'POLICY-CATEGORY-5', 'assigned_to' => $officer->id,
            'status' => 'open', 'case_type' => 'major', 'prior_offense_count' => 0,
            'suggested_sanction_category' => 5,
        ]);

        $this->actingAs($admin)->patch(route('admin.offense-policy.categories.archive', 5), [
            'reason' => 'Category temporarily withdrawn from new decisions.',
        ])->assertSessionHas('success');
        $this->actingAs($officer)->post(route('officer.cases.confirm-sanction', $case), [
            'sanction_category' => 5,
        ])->assertSessionHasErrors('sanction_category');

        $this->actingAs($admin)->patch(route('admin.offense-policy.categories.update', 5), [
            'number' => 5, 'intervention' => 'Expulsion',
            'reason' => 'Category restored under the approved policy.',
        ])->assertSessionHas('success');
        $this->actingAs($officer)->post(route('officer.cases.confirm-sanction', $case), [
            'sanction_category' => 5,
        ])->assertSessionHasNoErrors();
        $this->assertSame(5, $case->fresh()->assigned_sanction_category);
    }

    private function user(string $role): User
    {
        return User::create([
            'first_name' => 'Policy', 'last_name' => ucfirst($role),
            'email' => $role.'@policy.example.test', 'password' => Hash::make('password'),
            'role' => $role, 'status' => 'active',
        ]);
    }

    private function offenseInput(array $override = []): array
    {
        return array_replace([
            'code' => 'MAJOR-TEST', 'offense_type' => 'major',
            'title' => 'Test policy offense',
            'description' => 'An offense used to verify policy management.',
            'keywords' => 'policy, test',
            'handbook_section' => 'Section VI.E',
            'reason' => 'Approved handbook amendment for testing.',
        ], $override);
    }
}
