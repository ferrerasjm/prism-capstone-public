<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfficerAssignableUnitsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'last_name' => 'Admin',
            'first_name' => 'System',
            'email' => 'demo-user-001@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function checkboxNames(string $html): array
    {
        $start = strpos($html, 'academic_unit_ids[]');
        if ($start === false) {
            return [];
        }

        preg_match_all(
            '/name="academic_unit_ids\[\]" value="(\d+)"/',
            $html,
            $matches
        );

        return AcademicUnit::whereIn('id', $matches[1])->orderBy('name')->pluck('name')->all();
    }

    public function test_senior_high_school_is_not_offered_as_an_officer_department(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();
        $offered = $this->checkboxNames($html);

        $this->assertNotEmpty($offered, 'The department picker did not render.');
        $this->assertNotContains('Senior High School', $offered);
        $this->assertContains('Grade 11 (Senior High School)', $offered);
        $this->assertContains('Grade 12 (Senior High School)', $offered);
    }

    public function test_the_unit_itself_is_untouched_for_programs_and_onboarding(): void
    {
        // It still owns the SHS programs, so it must stay active and selectable elsewhere.
        $unit = AcademicUnit::where('name', 'Senior High School')->first();

        $this->assertNotNull($unit, 'The Senior High School unit is missing.');
        $this->assertTrue($unit->is_active);
        $this->assertGreaterThan(0, $unit->programs()->count());
    }

    public function test_an_officer_already_holding_it_keeps_the_checkbox(): void
    {
        $admin = $this->admin();
        $unit = AcademicUnit::where('name', 'Senior High School')->firstOrFail();

        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Legacy',
            'email' => 'legacy.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
            'academic_unit_id' => $unit->id,
        ]);
        $officer->academicUnits()->sync([$unit->id]);

        // Otherwise opening and saving this officer's form would silently drop the assignment.
        $html = $this->actingAs($admin)->get(route('admin.users.show', $officer))->assertOk()->getContent();

        $this->assertContains('Senior High School', $this->checkboxNames($html));
    }
}
