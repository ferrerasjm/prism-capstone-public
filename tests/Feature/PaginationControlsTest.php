<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\PerPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaginationControlsTest extends TestCase
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

    private function seedUsers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            User::create([
                'last_name' => 'Learner',
                'first_name' => sprintf('Student%03d', $i),
                'email' => sprintf('s%03d@students.nu-clark.edu.ph', $i),
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
            ]);
        }
    }

    private function rowCount(string $html): int
    {
        $start = strpos($html, '<tbody>');
        $end = strpos($html, '</tbody>');

        return substr_count(substr($html, $start, $end - $start), '<tr>');
    }

    public function test_the_default_page_size_is_unchanged(): void
    {
        $admin = $this->admin();
        $this->seedUsers(30);

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertSame(10, $this->rowCount($html));
        $this->assertStringContainsString('10 / page', $html);
    }

    public function test_the_page_size_can_be_changed(): void
    {
        $admin = $this->admin();
        $this->seedUsers(60);

        foreach ([25, 50] as $size) {
            $html = $this->actingAs($admin)
                ->get(route('admin.users.index', ['per_page' => $size]))
                ->assertOk()->getContent();

            $this->assertSame($size, $this->rowCount($html), "per_page={$size} did not change the row count.");
        }
    }

    public function test_an_out_of_range_page_size_falls_back_to_the_default(): void
    {
        $admin = $this->admin();
        $this->seedUsers(30);

        // An unbounded per_page would otherwise let anyone ask for every row at once.
        foreach (['5000', '0', '-1', 'all', '25; drop table users'] as $bad) {
            $html = $this->actingAs($admin)
                ->get(route('admin.users.index', ['per_page' => $bad]))
                ->assertOk()->getContent();

            $this->assertSame(
                PerPage::DEFAULT,
                $this->rowCount($html),
                "per_page={$bad} was not rejected.",
            );
        }
    }

    public function test_the_page_size_survives_paging_and_sorting(): void
    {
        $admin = $this->admin();
        $this->seedUsers(60);

        $html = $this->actingAs($admin)
            ->get(route('admin.users.index', ['per_page' => 25, 'sort' => 'name', 'direction' => 'asc']))
            ->assertOk()->getContent();

        // Page links must carry the size, or page 2 silently reverts to 10 rows.
        $this->assertMatchesRegularExpression('/href="[^"]*per_page=25[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*sort=name[^"]*"/', $html);

        $page2 = $this->actingAs($admin)
            ->get(route('admin.users.index', ['per_page' => 25, 'page' => 2]))
            ->assertOk()->getContent();

        $this->assertSame(25, $this->rowCount($page2));
    }

    public function test_changing_the_page_size_returns_to_the_first_page(): void
    {
        $admin = $this->admin();
        $this->seedUsers(60);

        $html = $this->actingAs($admin)
            ->get(route('admin.users.index', ['per_page' => 10, 'page' => 5]))
            ->assertOk()->getContent();

        // Page 5 of 10-per-page has no counterpart at 50 per page, so the size links drop it.
        preg_match_all('/href="([^"]*per_page=50[^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'No link to the 50-per-page option was rendered.');

        foreach ($matches[1] as $url) {
            // Parsed rather than substring-matched: "per_page=50" contains "page=5".
            parse_str((string) parse_url(html_entity_decode($url), PHP_URL_QUERY), $query);

            $this->assertArrayNotHasKey('page', $query, "Size link kept the page number: {$url}");
            $this->assertSame('50', $query['per_page'] ?? null);
        }
    }

    public function test_the_number_strip_collapses_with_an_ellipsis(): void
    {
        $admin = $this->admin();
        $this->seedUsers(200);

        $html = $this->actingAs($admin)
            ->get(route('admin.users.index', ['page' => 10]))
            ->assertOk()->getContent();

        // 201 users at 10 a page is 21 pages - far too many to list one by one.
        $this->assertStringContainsString('prism-page-gap', $html);
        $this->assertLessThan(
            21,
            substr_count($html, 'class="page-link"'),
            'Every page number was listed instead of being collapsed.',
        );
    }

    public function test_the_size_picker_is_hidden_when_everything_fits_on_one_page(): void
    {
        $admin = $this->admin();
        $this->seedUsers(3);

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('prism-per-page', $html);
    }
}
