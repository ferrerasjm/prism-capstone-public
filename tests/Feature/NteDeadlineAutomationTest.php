<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Notifications\NteDeadlineReminderNotification;
use App\Notifications\NteOverdueNotification;
use App\Notifications\NteOverdueStudentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NteDeadlineAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_one_reminder_when_deadline_is_within_two_days(): void
    {
        $notice = $this->pendingNte(today()->addDays(2)->toDateString());

        $this->artisan('cases:process-nte-deadlines')->assertSuccessful();
        $this->artisan('cases:process-nte-deadlines')->assertSuccessful();

        $this->assertNotNull($notice->fresh()->deadline_reminder_sent_at);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'type' => NteDeadlineReminderNotification::class,
            'notifiable_id' => $notice->case->student_id,
        ]);
    }

    public function test_deadline_date_remains_open_until_the_day_has_finished(): void
    {
        $notice = $this->pendingNte(today()->toDateString());

        $this->assertFalse($notice->isOverdue());

        $this->artisan('cases:process-nte-deadlines')->assertSuccessful();

        $this->assertNull($notice->fresh()->overdue_flagged_at);
    }

    public function test_it_flags_an_overdue_notice_only_once(): void
    {
        $notice = $this->pendingNte(today()->subDay()->toDateString());

        $this->artisan('cases:process-nte-deadlines')
            ->expectsOutput('Sent 0 NTE reminder(s); flagged 1 overdue NTE(s).')
            ->assertSuccessful();
        $firstFlag = $notice->fresh()->overdue_flagged_at;

        $this->assertDatabaseHas('notifications', [
            'type' => NteOverdueNotification::class,
            'notifiable_id' => $notice->case->assigned_to,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => NteOverdueStudentNotification::class,
            'notifiable_id' => $notice->case->student_id,
        ]);

        $this->artisan('cases:process-nte-deadlines')
            ->expectsOutput('Sent 0 NTE reminder(s); flagged 0 overdue NTE(s).')
            ->assertSuccessful();

        $this->assertNotNull($firstFlag);
        $this->assertTrue($firstFlag->equalTo($notice->fresh()->overdue_flagged_at));
        $this->assertDatabaseCount('notifications', 2);
    }

    private function pendingNte(string $deadline): CaseNotice
    {
        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Deadline',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $student = User::create([
            'last_name' => 'Student',
            'first_name' => 'Deadline',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'NTE-'.fake()->unique()->numerify('#####'),
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'major',
            'case_type_locked' => true,
            'status' => 'pending_nte',
            'prior_offense_count' => 0,
        ]);

        return CaseNotice::create([
            'case_id' => $case->id,
            'notice_type' => 'notice_to_explain',
            'issued_by' => $officer->id,
            'issued_at' => now(),
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '41',
            'incident_date' => today()->subDay(),
            'response_deadline' => $deadline,
            'is_voided' => false,
            'created_at' => now(),
        ]);
    }
}
