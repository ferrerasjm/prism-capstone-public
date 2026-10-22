<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserListSortingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph', 'Admin', 'System', '2026-01-01 08:00:00');
    }

    private function user(
        string $role,
        string $email,
        string $lastName,
        string $firstName,
        string $createdAt,
    ): User {
        $user = User::create([
            'last_name' => $lastName,
            'first_name' => $firstName,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $user->forceFill(['created_at' => $createdAt])->save();

        return $user->refresh();
    }

    /** Just the table body - the layout chrome repeats the signed-in user's name. */
    private function tableBody(string $html): string
    {
        $start = strpos($html, '<tbody>');
        $end = strpos($html, '</tbody>');

        $this->assertNotFalse($start, 'No table body in the response.');
        $this->assertNotFalse($end, 'No table body in the response.');

        return substr($html, $start, $end - $start);
    }

    /** The order the rows appear in the rendered table. */
    private function renderedOrder(string $html, array $names): array
    {
        $body = $this->tableBody($html);
        $positions = [];

        foreach ($names as $name) {
            $at = strpos($body, $name);
            if ($at !== false) {
                $positions[$name] = $at;
            }
        }

        asort($positions);

        return array_keys($positions);
    }

    public function test_the_list_defaults_to_newest_accounts_first(): void
    {
        $admin = $this->admin();
        $this->user('discipline_officer', 'oldest@nu-clark.edu.ph', 'Alpha', 'Oldest', '2026-02-01 09:00:00');
        $this->user('discipline_officer', 'newest@nu-clark.edu.ph', 'Zulu', 'Newest', '2026-05-01 09:00:00');

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertSame(
            ['Zulu, Newest', 'Alpha, Oldest', 'Admin, System'],
            $this->renderedOrder($html, ['Zulu, Newest', 'Alpha, Oldest', 'Admin, System']),
        );
    }

    public function test_admin_can_filter_student_accounts_by_department_and_program(): void
    {
        $admin = $this->admin();
        $selectedUnit = AcademicUnit::query()->firstOrFail();
        $selectedProgram = $selectedUnit->programs()->firstOrFail();
        $otherUnit = AcademicUnit::create(['name' => 'User Filter Department', 'is_active' => true]);
        $otherProgram = AcademicProgram::create([
            'academic_unit_id' => $otherUnit->id,
            'name' => 'User Filter Program',
            'is_active' => true,
        ]);
        $selectedStudent = $this->user('student', 'selected-student@nu-clark.edu.ph', 'Selected', 'Student', '2026-06-01 09:00:00');
        $otherStudent = $this->user('student', 'other-student@nu-clark.edu.ph', 'Other', 'Student', '2026-06-01 09:00:00');
        StudentProfile::create([
            'user_id' => $selectedStudent->id,
            'student_number' => '2026-00001',
            'program' => $selectedProgram->name,
        ]);
        StudentProfile::create([
            'user_id' => $otherStudent->id,
            'student_number' => '2026-00002',
            'program' => $otherProgram->name,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['department' => $selectedUnit->id, 'program' => $selectedProgram->id]))
            ->assertOk()
            ->assertSee('Selected, Student')
            ->assertDontSee('Other, Student')
            ->assertSee('Department')
            ->assertSee('Program')
            ->assertSee('Email');
    }

    public function test_the_name_column_sorts_both_ways(): void
    {
        $admin = $this->admin();
        $this->user('discipline_officer', 'b@nu-clark.edu.ph', 'Bravo', 'Bee', '2026-05-01 09:00:00');
        $this->user('discipline_officer', 'a@nu-clark.edu.ph', 'Alpha', 'Ace', '2026-02-01 09:00:00');

        $ascending = $this->actingAs($admin)
            ->get(route('admin.users.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()->getContent();

        $this->assertSame(
            ['Alpha, Ace', 'Bravo, Bee'],
            $this->renderedOrder($ascending, ['Alpha, Ace', 'Bravo, Bee']),
        );

        $descending = $this->actingAs($admin)
            ->get(route('admin.users.index', ['sort' => 'name', 'direction' => 'desc']))
            ->assertOk()->getContent();

        $this->assertSame(
            ['Bravo, Bee', 'Alpha, Ace'],
            $this->renderedOrder($descending, ['Bravo, Bee', 'Alpha, Ace']),
        );
    }

    public function test_accounts_created_in_the_same_second_keep_a_stable_order_across_pages(): void
    {
        $admin = $this->admin();

        // What a bulk student import looks like: every row shares a created_at.
        for ($i = 1; $i <= 25; $i++) {
            $this->user(
                'student',
                sprintf('student%02d@students.nu-clark.edu.ph', $i),
                'Import',
                sprintf('Student%02d', $i),
                '2026-06-01 10:00:00',
            );
        }

        $seen = [];

        foreach ([1, 2, 3] as $page) {
            $html = $this->actingAs($admin)
                ->get(route('admin.users.index', ['page' => $page]))
                ->assertOk()->getContent();

            // A row repeats the name in its aria-labels, so count each student once per page.
            preg_match_all('/Import, Student(\d{2})/', $this->tableBody($html), $matches);
            $onThisPage = array_unique($matches[1]);

            $overlap = array_intersect($seen, $onThisPage);
            $this->assertEmpty(
                $overlap,
                'These users appeared on more than one page, so the ordering is not stable: '
                    .implode(', ', $overlap),
            );

            $seen = array_merge($seen, $onThisPage);
        }

        $this->assertCount(25, $seen, 'Some users were skipped between pages.');
    }

    public function test_an_unknown_sort_column_falls_back_to_the_default(): void
    {
        $admin = $this->admin();
        $this->user('discipline_officer', 'oldest@nu-clark.edu.ph', 'Alpha', 'Oldest', '2026-02-01 09:00:00');
        $this->user('discipline_officer', 'newest@nu-clark.edu.ph', 'Zulu', 'Newest', '2026-05-01 09:00:00');

        $html = $this->actingAs($admin)
            ->get(route('admin.users.index', ['sort' => 'password', 'direction' => 'drop table']))
            ->assertOk()->getContent();

        $this->assertSame(
            ['Zulu, Newest', 'Alpha, Oldest'],
            $this->renderedOrder($html, ['Zulu, Newest', 'Alpha, Oldest']),
        );
    }

    public function test_names_use_surname_first_name_and_optional_middle_initial(): void
    {
        $admin = $this->admin();
        $withMiddleName = $this->user(
            'discipline_officer',
            'middle@nu-clark.edu.ph',
            'Santos',
            'Maria',
            '2026-05-01 09:00:00',
        );
        $withMiddleName->update(['middle_name' => 'Patricia']);
        $this->user(
            'discipline_officer',
            'without-middle@nu-clark.edu.ph',
            'Reyes',
            'Juan',
            '2026-04-01 09:00:00',
        );

        $body = $this->tableBody(
            $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('Santos, Maria P.', $body);
        $this->assertStringContainsString('Reyes, Juan', $body);
        $this->assertStringNotContainsString('Reyes, Juan .', $body);
    }

    public function test_sorting_survives_filtering_and_pagination_links(): void
    {
        $admin = $this->admin();

        for ($i = 1; $i <= 15; $i++) {
            $this->user(
                'student',
                sprintf('s%02d@students.nu-clark.edu.ph', $i),
                'Learner',
                sprintf('Student%02d', $i),
                '2026-06-01 10:00:00',
            );
        }

        $html = $this->actingAs($admin)
            ->get(route('admin.users.index', ['sort' => 'name', 'direction' => 'asc', 'role' => 'student']))
            ->assertOk()->getContent();

        // The filter form must carry the sort, or changing a filter resets it.
        $this->assertStringContainsString('name="sort" value="name"', $html);
        $this->assertStringContainsString('name="direction" value="asc"', $html);

        // Pagination links must carry both the filter and the sort.
        $this->assertMatchesRegularExpression('/href="[^"]*sort=name[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*direction=asc[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*role=student[^"]*"/', $html);
    }
}
