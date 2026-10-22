<?php

declare(strict_types=1);

namespace Tests\Unit\ReportingActivityLog;

use App\Models\AuditLog;
use Tests\TestCase;

/**
 * Module 6 - Reporting and Activity Log
 * Component: Audit Activity Summaries (App\Models\AuditLog::summary)
 * Test Cycle No. 1 - 5 test cases.
 *
 * summary() derives the human-readable activity sentence from the stored
 * module, action and value snapshot. The models are built in memory so no
 * audit row is written and no announcement side effect is triggered.
 */
class AuditActivitySummaryTest extends TestCase
{
    /** Scenario 1 - Normal: account status changes are described in plain language. */
    public function test_account_status_changes_are_described_in_plain_language(): void
    {
        $activated = $this->log('users', 'update', ['status' => 'active']);
        $deactivated = $this->log('users', 'update', ['status' => 'inactive']);
        $other = $this->log('users', 'update', ['status' => 'pending_setup']);

        $this->assertSame('An account has been activated.', $activated->summary());
        $this->assertSame('An account has been deactivated.', $deactivated->summary());
        $this->assertSame('An account has been returned to pending setup.', $other->summary());
    }

    /** Scenario 2 - Normal: account creation, removal and import each have their own sentence. */
    public function test_account_creation_removal_and_import_have_their_own_sentences(): void
    {
        $this->assertSame('An account has been created.', $this->log('users', 'create')->summary());
        $this->assertSame('An account has been removed.', $this->log('users', 'delete')->summary());
        $this->assertSame('Student accounts have been imported.', $this->log('users', 'import')->summary());
    }

    /** Scenario 3 - Normal: authentication events are summarised separately. */
    public function test_authentication_events_are_summarised_separately(): void
    {
        $this->assertSame('A user has signed in.', $this->log('auth', 'login')->summary());
        $this->assertSame('A user has signed out.', $this->log('auth', 'logout')->summary());
        $this->assertSame(
            'An account security setting has been updated.',
            $this->log('auth', 'update')->summary()
        );
    }

    /** Scenario 4 - Boundary: service office activation state decides the wording. */
    public function test_service_office_activation_state_decides_the_wording(): void
    {
        $added = $this->log('university_service_offices', 'create');
        $removed = $this->log('university_service_offices', 'update', ['is_active' => false], ['is_active' => true]);
        $restored = $this->log('university_service_offices', 'update', ['is_active' => true], ['is_active' => false]);
        $renamed = $this->log('university_service_offices', 'update', ['name' => 'New name'], ['name' => 'Old name']);

        $this->assertSame('A University Service Office has been added.', $added->summary());
        $this->assertSame('A University Service Office has been removed.', $removed->summary());
        $this->assertSame('A University Service Office has been restored.', $restored->summary());
        $this->assertSame('A University Service Office has been updated.', $renamed->summary());
    }

    /**
     * Scenario 5 - Invalid / boundary: every other module falls back to the
     * generic resource sentence, choosing its article from the first letter
     * of the resource name.
     */
    public function test_other_modules_fall_back_to_the_generic_resource_sentence(): void
    {
        $this->assertSame('A case has been voided.', $this->log('cases', 'void')->summary());
        $this->assertSame(
            'An incident report has been created.',
            $this->log('incident_reports', 'create')->summary()
        );
        $this->assertSame(
            'A student handbook has been archived.',
            $this->log('student_handbooks', 'archive')->summary()
        );
        // The article follows the spoken sound rather than the letter, so the
        // consonant-sounding "University ..." resources read "A" (see
        // AuditLog::articleFor()).
        $this->assertSame(
            'A University Service assignment has been updated.',
            $this->log('us_assignments', 'update')->summary()
        );
    }

    private function log(
        string $module,
        string $action,
        ?array $newValues = null,
        ?array $oldValues = null
    ): AuditLog {
        return new AuditLog([
            'user_id' => 1,
            'action' => $action,
            'module' => $module,
            'record_id' => 1,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => now(),
        ]);
    }
}
