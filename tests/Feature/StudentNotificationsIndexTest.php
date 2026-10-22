<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Notifications\CaseCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentNotificationsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_beyond_the_dropdown_limit_are_reachable_on_the_full_page(): void
    {
        $student = $this->user('student', 'notifications-index-student@example.test');
        $officer = $this->user('discipline_officer', 'notifications-index-officer@example.test');

        // Distinct timestamps: the index orders by created_at, so notifications created
        // within the same second would paginate in an arbitrary order.
        $base = Carbon::parse('2026-08-01 08:00:00');

        for ($i = 0; $i < 12; $i++) {
            Carbon::setTestNow($base->copy()->addMinutes($i));

            $case = DisciplinaryCase::create([
                'case_number' => 'DC-NOTIF-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'student_id' => $student->id,
                'assigned_to' => $officer->id,
                'status' => 'open',
                'prior_offense_count' => 0,
            ]);
            $student->notify(new CaseCreatedNotification($case));
        }

        Carbon::setTestNow();

        $dashboard = $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
        $dashboard->assertSee('View all notifications');

        // 12 notifications do not fit on one 10-row page, so "reachable" means across the pages.
        $seen = [];

        foreach ([1, 2] as $page) {
            $response = $this->actingAs($student)
                ->get(route('student.notifications.index', ['page' => $page]))
                ->assertOk();

            $html = $response->getContent();
            $this->assertStringNotContainsString('You have no notifications yet.', $html);

            // Only the list itself - the header bell repeats the newest items on every page.
            $start = strpos($html, '<div class="student-notifications-list">');
            $this->assertNotFalse($start, 'The notifications list did not render.');
            $list = substr($html, $start);

            preg_match_all('/DC-NOTIF-\d+/', $list, $matches);
            $onThisPage = array_unique($matches[0]);

            $this->assertEmpty(
                array_intersect($seen, $onThisPage),
                'A notification appeared on more than one page, so the ordering is not stable.',
            );

            $seen = array_merge($seen, $onThisPage);
        }

        sort($seen);
        $expected = array_map(
            fn (int $i): string => 'DC-NOTIF-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            range(0, 11),
        );
        sort($expected);

        $this->assertSame($expected, $seen);
        $this->assertSame(12, $student->notifications()->count());
    }

    public function test_mark_all_read_button_only_shows_when_there_are_unread_notifications(): void
    {
        $student = $this->user('student', 'notifications-index-read@example.test');
        $officer = $this->user('discipline_officer', 'notifications-index-read-officer@example.test');
        $case = DisciplinaryCase::create([
            'case_number' => 'DC-NOTIF-READ',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);
        $student->notify(new CaseCreatedNotification($case));

        $this->actingAs($student)
            ->get(route('student.notifications.index'))
            ->assertOk()
            ->assertSee('Mark All as Read');

        $student->unreadNotifications->markAsRead();

        $this->actingAs($student)
            ->get(route('student.notifications.index'))
            ->assertOk()
            ->assertDontSee('Mark All as Read');
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Notification',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
