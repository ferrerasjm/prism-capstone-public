<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\CaseNotice;
use App\Models\CsvImportLog;
use App\Models\DisciplinaryCase;
use App\Models\IncidentAttachment;
use App\Models\IncidentReport;
use App\Models\IncidentReportSubject;
use App\Models\OffenseCatalog;
use App\Models\RiskScore;
use App\Models\SanctionMatrix;
use App\Models\StudentHandbook;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\AccountSetupLinkNotification;
use App\Notifications\CaseCreatedNotification;
use App\Notifications\CaseExplanationSubmittedNotification;
use App\Notifications\NteIssuedNotification;
use App\Services\CaseService;
use App\Services\IncidentReportService;
use App\Services\SanctionRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * INTEGRATION TEST GROUP 2 — Incident Reporting to Violation Case Documentation.
 *
 * Exercises the seam between the reporting-user portal, attachment storage and
 * the AttachmentSecurityService, the yearly reference-number allocator, the
 * incident-to-case conversion (with notifications and risk recomputation), the
 * Student Handbook edition resolver used when issuing notices, the NTE deadline
 * scheduler command, and the student explanation to officer evaluation handoff.
 *
 * Run only this group:
 *   php artisan test tests/Feature/Integration/IncidentReportingCaseDocumentationIntegrationTest.php
 */
class IncidentReportingCaseDocumentationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * IT-02-01 — Reporter portal ↔ AttachmentSecurityService ↔ local storage disk
     * ↔ incident report + audit trail.
     */
    public function test_reporter_submission_allocates_a_reference_number_and_stores_a_scanned_attachment(): void
    {
        Storage::fake('local');

        $reporter = $this->user('reporting_user', 'it02-reporter@nu-clark.edu.ph', ['office_name' => 'Security Office']);
        $student = $this->student('it02-subject@students.nu-clark.edu.ph', '2026-100001');

        $this->actingAs($reporter)
            ->post(route('reporter.reports.store'), [
                'student_id' => $student->id,
                'date_of_incident' => now()->subDay()->toDateString(),
                'time_of_incident' => '14:30',
                'location' => 'Main Gate',
                'description' => 'The student refused an ID inspection at the main gate and left the checkpoint.',
                'disclaimer' => '1',
                'attachments' => [
                    UploadedFile::fake()->create('gate evidence.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('reporter.reports.index'))
            ->assertSessionHas('success');

        $report = IncidentReport::firstOrFail();

        $this->assertSame('IR-'.now()->year.'-0001', $report->report_number);
        $this->assertSame('security_report', $report->source_type);
        $this->assertSame('pending_review', $report->status);
        $this->assertSame($student->id, $report->student_id);

        $attachment = IncidentAttachment::firstOrFail();

        $this->assertSame($report->id, $attachment->incident_report_id);
        $this->assertSame('gate-evidence.pdf', $attachment->file_name);
        $this->assertSame('pdf', $attachment->file_type);
        $this->assertSame('not_configured', $attachment->scan_status);
        $this->assertNotNull($attachment->file_hash);
        $this->assertSame($reporter->id, $attachment->uploaded_by);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $reporter->id,
            'action' => 'create',
            'module' => 'incident_reports',
            'record_id' => $report->id,
        ]);
    }

    /**
     * IT-02-02 — Incident report with multiple named subjects ↔ case creation ↔
     * student notifications ↔ RiskScoringService recomputation.
     */
    public function test_converting_a_multi_subject_report_opens_one_unclassified_case_per_student(): void
    {
        $officer = $this->user('discipline_officer', 'it02-officer@nu-clark.edu.ph');
        $reporter = $this->user('reporting_user', 'it02-faculty@nu-clark.edu.ph');
        $first = $this->student('it02-first@students.nu-clark.edu.ph', '2026-100002');
        $second = $this->student('it02-second@students.nu-clark.edu.ph', '2026-100003');

        $report = IncidentReport::create([
            'report_number' => 'IR-'.now()->year.'-0007',
            'source_type' => 'faculty_report',
            'reported_by_user_id' => $reporter->id,
            'reported_by_name' => $reporter->full_name,
            'student_id' => $first->id,
            'date_of_incident' => now()->subDays(2)->toDateString(),
            'description' => 'Two students were involved in a shouting altercation inside the laboratory.',
            'violation_type' => 'Others',
            'status' => 'under_review',
        ]);

        IncidentReportSubject::create([
            'incident_report_id' => $report->id,
            'student_id' => $second->id,
            'created_at' => now(),
        ]);

        $cases = app(IncidentReportService::class)->convertToCase($report, $officer);

        $this->assertCount(2, $cases);
        $this->assertSame(
            ['DC-'.now()->year.'-0001', 'DC-'.now()->year.'-0002'],
            collect($cases)->pluck('case_number')->all()
        );

        foreach ([$first, $second] as $subject) {
            $case = DisciplinaryCase::where('student_id', $subject->id)->firstOrFail();

            $this->assertNull($case->case_type, 'A converted report must stay unclassified until an offense is confirmed.');
            $this->assertFalse((bool) $case->case_type_locked);
            $this->assertSame('open', $case->status);
            $this->assertSame($report->id, $case->incident_report_id);
            $this->assertSame($officer->id, $case->assigned_to);

            $this->assertDatabaseHas('notifications', [
                'type' => CaseCreatedNotification::class,
                'notifiable_id' => $subject->id,
            ]);
            $this->assertNotNull(
                RiskScore::where('student_id', $subject->id)->first(),
                'Opening a case must recompute the student risk profile.'
            );
        }

        $report->refresh();

        $this->assertSame('converted_to_case', $report->status);
        $this->assertSame($officer->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'incident_reports',
            'record_id' => $report->id,
            'action' => 'update',
        ]);
    }

    /**
     * IT-02-03 — Confirmed charge ↔ Student Handbook edition resolver ↔ NTE
     * issuance ↔ procedural deadline ↔ student notification.
     */
    public function test_issuing_an_nte_snapshots_the_handbook_edition_and_the_five_day_response_deadline(): void
    {
        $officer = $this->user('discipline_officer', 'it02-nte-officer@nu-clark.edu.ph');
        $student = $this->student('it02-nte-student@students.nu-clark.edu.ph', '2026-100004');
        $offense = $this->offense('MAJOR-02', 'major', 'University ID violation');
        $incidentDate = now()->subDays(3)->toDateString();

        $handbook = StudentHandbook::create([
            'title' => 'NU Clark Student Handbook',
            'edition' => '2026 Edition',
            'academic_year' => '2026',
            'effective_date' => now()->subMonths(6)->toDateString(),
            'file_path' => 'student-handbooks/handbook-2026.pdf',
            'original_filename' => 'handbook-2026.pdf',
            'file_size' => 2048,
            'checksum_sha256' => str_repeat('a', 64),
            'status' => 'published',
            'published_at' => now()->subMonths(6),
        ]);

        $case = $this->openCase($officer, $student, 'IT0203', $incidentDate);
        app(CaseService::class)->confirmOffense($case, $offense->id, $officer->id);
        app(CaseService::class)->confirmSanction($case->fresh(), 2, $officer->id);

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.issue-nte', $case), [
                'incident_date' => $incidentDate,
                'additional_content' => 'Submit a written explanation to the Student Discipline Office.',
            ])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel')
            ->assertSessionHas('success');

        $case->refresh();
        $notice = CaseNotice::where('case_id', $case->id)
            ->where('notice_type', 'notice_to_explain')
            ->firstOrFail();

        $this->assertSame('pending_nte', $case->status);
        $this->assertTrue((bool) $case->case_type_locked);
        $this->assertSame($handbook->id, $notice->student_handbook_id);
        $this->assertSame('Section VI.E', $notice->handbook_section);
        $this->assertSame(today()->addDays(5)->toDateString(), $notice->response_deadline->toDateString());
        $this->assertFalse((bool) $notice->is_voided);
        $this->assertSame($offense->code, $notice->additional_content['offenses'][0]['code']);

        $this->assertDatabaseHas('notifications', [
            'type' => NteIssuedNotification::class,
            'notifiable_id' => $student->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'case_notices',
            'record_id' => $notice->id,
            'action' => 'create',
        ]);
    }

    /**
     * IT-02-04 — Admin roster import ↔ StudentImportService ↔ AccountSetupService
     * ↔ activation email ↔ AnnouncementService ↔ import log + audit ↔ account
     * setup link consumption.
     *
     * This is the provisioning chain that creates the student records every
     * incident report and case is filed against, ending at the point where the
     * imported account can actually authenticate.
     */
    public function test_roster_import_provisions_accounts_whose_issued_setup_link_activates_the_student(): void
    {
        Notification::fake();

        $admin = $this->user('super_admin', 'it02-demo-user-001@nu-clark.edu.ph');
        $existing = $this->student('it02-existing@students.nu-clark.edu.ph', '2026-100005');

        $csv = implode("\n", [
            'student_number,last_name,first_name,email,middle_name',
            '2026-100008,Aquino,Bianca,aquinob@students.nu-clark.edu.ph,Lim',
            '2026-100005,Duplicate,Record,duplicate@students.nu-clark.edu.ph,',
            '2026-100009,Offdomain,Rejected,rejected@example.com,',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.import.store'), [
                'csv_file' => UploadedFile::fake()->createWithContent('roster.csv', $csv),
            ])
            ->assertRedirect();

        $log = CsvImportLog::firstOrFail();

        $this->assertSame(3, $log->total_rows);
        $this->assertSame(1, $log->created_count);
        $this->assertSame(1, $log->skipped_count);
        $this->assertSame(1, $log->error_count);
        $this->assertSame($admin->id, $log->imported_by);
        $this->assertStringContainsString('already exists', $log->results_json[1]['reason']);
        $this->assertStringContainsString('domain', $log->results_json[2]['reason']);

        $imported = User::where('email', 'aquinob@students.nu-clark.edu.ph')->firstOrFail();

        $this->assertSame('pending_setup', $imported->status);
        $this->assertSame($admin->id, $imported->created_by);
        $this->assertSame('2026-100008', $imported->institutional_id);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $imported->id,
            'student_number' => '2026-100008',
        ]);
        $this->assertDatabaseHas('announcements', [
            'target_user_id' => $imported->id,
            'created_by' => $admin->id,
            'title' => 'Your account was created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'import',
            'module' => 'users',
        ]);

        // The activation link is issued as a hashed token; only the email carries the plain value.
        $setupToken = $imported->accountSetupToken;
        $this->assertNotNull($setupToken);
        $this->assertNull($setupToken->consumed_at);

        $plainToken = null;
        Notification::assertSentTo(
            $imported,
            AccountSetupLinkNotification::class,
            function (AccountSetupLinkNotification $notification) use (&$plainToken): bool {
                $plainToken = (new \ReflectionProperty($notification, 'token'))->getValue($notification);

                return true;
            }
        );
        $this->assertNotNull($plainToken);
        $this->assertSame(hash('sha256', $plainToken), $setupToken->token_hash);

        // account-setup.show sits behind the 'guest' middleware, so the admin's
        // authenticated session must end before the link can be followed.
        $this->post(route('logout'));

        // The link the import issued is the one that works.
        $this->get(route('account-setup.show', ['token' => $plainToken]))->assertOk();

        $this->post(route('account-setup.complete'), [
            'token' => $plainToken,
            'password' => 'Prism2026pass',
            'password_confirmation' => 'Prism2026pass',
        ])->assertRedirect(route('onboarding.profile'));

        $imported->refresh();

        $this->assertTrue(Hash::check('Prism2026pass', $imported->password));
        $this->assertNotNull($imported->accountSetupToken->consumed_at);
        $this->assertSame('pending_setup', $imported->status, 'Students stay pending until onboarding is acknowledged.');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $imported->id,
            'module' => 'auth',
            'action' => 'update',
        ]);

        // The consumed link cannot be replayed.
        $this->post(route('logout'));
        $this->get(route('account-setup.show', ['token' => $plainToken]))->assertStatus(410);

        $this->assertTrue(
            User::where('email', 'duplicate@students.nu-clark.edu.ph')->doesntExist(),
            'A row reusing an existing student number must be skipped, not duplicated.'
        );
        $this->assertSame('2026-100005', $existing->studentProfile->student_number);
    }

    /**
     * IT-02-05 — Student explanation portal ↔ NTE acknowledgement ↔ officer
     * evaluation ↔ SanctionRecommendationService path recommendation.
     */
    public function test_student_explanation_flows_into_an_immutable_officer_evaluation_and_path_recommendation(): void
    {
        $officer = $this->user('discipline_officer', 'it02-eval-officer@nu-clark.edu.ph');
        $student = $this->student('it02-eval-student@students.nu-clark.edu.ph', '2026-100007');
        $offense = $this->offense('MAJOR-05', 'major', 'Grave misconduct against university personnel');
        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id,
            'offense_count' => 1,
            'sanction_category' => 3,
        ]);

        $case = $this->pendingNteCase($officer, $student, $offense, 'IT0205', today()->addDays(4), 'major');
        $case->update(['assigned_sanction_category' => 3]);

        $explanationText = 'I acknowledge the incident and I would like to explain the circumstances that led to it in full detail.';

        $this->actingAs($student)
            ->post(route('student.cases.explanation.store', $case), [
                'explanation_text' => $explanationText,
            ])
            ->assertRedirect(route('student.cases.show', $case))
            ->assertSessionHas('success');

        $case->refresh();
        $notice = CaseNotice::where('case_id', $case->id)->firstOrFail();

        $this->assertSame('awaiting_explanation', $case->status);
        $this->assertNotNull($notice->student_acknowledged_at);
        $this->assertDatabaseHas('case_explanations', [
            'case_id' => $case->id,
            'submission_channel' => 'portal',
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => CaseExplanationSubmittedNotification::class,
            'notifiable_id' => $officer->id,
        ]);

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.save-evaluation', $case), [
                'admission_type' => 'full_admission',
                'evaluation_notes' => 'The student admitted the act during the intake interview.',
            ])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel')
            ->assertSessionHas('success');

        $case->refresh();
        $explanation = $case->explanation()->firstOrFail();

        $this->assertSame('under_review', $case->status);
        $this->assertSame('upcc_referral', $case->path_recommendation);
        $this->assertSame('Category 3+ sanction requires UPCC referral.', $case->path_recommendation_reason);
        $this->assertSame('full_admission', $explanation->admission_type);
        $this->assertSame($officer->id, $explanation->evaluated_by);
        $this->assertNotNull($explanation->evaluated_at);

        // The evaluation is a due-process record: re-evaluating the same explanation is refused.
        $thrown = null;
        try {
            app(CaseService::class)->saveEvaluation(
                $case->fresh(),
                $explanation->fresh(),
                'full_denial',
                'Attempted second evaluation.',
                $officer->id,
                app(SanctionRecommendationService::class),
            );
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertNotNull($thrown, 'A saved explanation evaluation must be immutable.');
        $this->assertSame('full_admission', $explanation->fresh()->admission_type);
    }

    private function user(string $role, string $email, array $extra = []): User
    {
        return User::create(array_merge([
            'last_name' => 'Dela Cruz',
            'first_name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ], $extra));
    }

    private function student(string $email, string $studentNumber): User
    {
        $student = $this->user('student', $email);

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => $studentNumber,
            'program' => 'BS Information Technology',
            'year_level' => '3',
        ]);

        return $student;
    }

    private function offense(string $code, string $type, string $title): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => $title,
            'description' => $title.'.',
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '48',
            'keywords' => [strtolower($title)],
            'created_at' => now(),
        ]);
    }

    private function openCase(User $officer, User $student, string $suffix, string $incidentDate): DisciplinaryCase
    {
        $report = IncidentReport::create([
            'report_number' => "IR-2026-{$suffix}",
            'source_type' => 'faculty_report',
            'reported_by_user_id' => $officer->id,
            'reported_by_name' => $officer->full_name,
            'student_id' => $student->id,
            'date_of_incident' => $incidentDate,
            'description' => 'The student was documented committing the reported violation on campus.',
            'violation_type' => 'Others',
            'status' => 'under_review',
        ]);

        return DisciplinaryCase::create([
            'case_number' => "DC-2026-{$suffix}",
            'incident_report_id' => $report->id,
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => null,
            'case_type_locked' => false,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);
    }

    private function pendingNteCase(
        User $officer,
        User $student,
        OffenseCatalog $offense,
        string $suffix,
        \Illuminate\Support\Carbon $deadline,
        string $caseType = 'minor',
    ): DisciplinaryCase {
        $case = DisciplinaryCase::create([
            'case_number' => "DC-2026-{$suffix}",
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => $caseType,
            'case_type_locked' => true,
            'status' => 'pending_nte',
            'confirmed_offense_id' => $offense->id,
            'prior_offense_count' => 0,
        ]);

        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'prior_offense_count' => 0,
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);

        CaseNotice::create([
            'case_id' => $case->id,
            'notice_type' => 'notice_to_explain',
            'issued_by' => $officer->id,
            'issued_at' => now()->subDay(),
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '48',
            'incident_date' => today()->subDays(6)->toDateString(),
            'response_deadline' => $deadline->toDateString(),
            'additional_content' => ['offenses' => [], 'notes' => null],
            'is_voided' => false,
            'created_at' => now()->subDay(),
        ]);

        return $case;
    }
}
