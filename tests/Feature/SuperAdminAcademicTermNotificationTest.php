<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\User;
use App\Notifications\AcademicPeriodStartedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminAcademicTermNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_only_the_current_term_has_the_active_status(): void
    {
        Carbon::setTestNow('2026-08-15 10:00:00');
        $admin = $this->admin();
        $ended = $this->period('2025-2026', '3rd Term', '2026-05-01', '2026-07-31');
        $current = $this->period('2026-2027', '1st Term', '2026-08-01', '2026-12-20');
        $upcoming = $this->period('2026-2027', '2nd Term', '2027-01-05', '2027-04-30');

        $this->assertSame('Ended', $ended->statusLabel());
        $this->assertSame('Active', $current->statusLabel());
        $this->assertSame('Upcoming', $upcoming->statusLabel());

        $this->actingAs($admin)
            ->get(route('admin.academic-periods.index'))
            ->assertOk()
            ->assertSee('Ended')
            ->assertSee('Active')
            ->assertSee('Upcoming');
    }

    public function test_super_admin_is_notified_once_when_a_term_starts(): void
    {
        Carbon::setTestNow('2026-08-01 00:10:00');
        $admin = $this->admin();
        $period = $this->period('2026-2027', '1st Term', '2026-08-01', '2026-12-20');
        $this->period('2026-2027', '2nd Term', '2027-01-05', '2027-04-30');

        $this->artisan('academic-periods:notify-started')->assertSuccessful();
        $this->artisan('academic-periods:notify-started')->assertSuccessful();

        $notifications = $admin->notifications()
            ->where('type', AcademicPeriodStartedNotification::class)
            ->get();

        $this->assertCount(1, $notifications);
        $this->assertSame($period->id, $notifications->first()->data['academic_period_id']);
        $this->assertSame('New academic term started', $notifications->first()->data['title']);
    }

    private function period(
        string $academicYear,
        string $term,
        string $startsOn,
        string $endsOn
    ): AcademicPeriod {
        return AcademicPeriod::create([
            'academic_year' => $academicYear,
            'term_name' => $term,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
