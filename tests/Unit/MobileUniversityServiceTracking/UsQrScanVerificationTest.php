<?php

declare(strict_types=1);

namespace Tests\Unit\MobileUniversityServiceTracking;

use App\Models\UsAssignment;
use App\Services\USAssignmentService;
use OTPHP\TOTP;
use Tests\TestCase;

/**
 * Module 7 - Mobile University Service Tracking
 * Component: QR Scan Code Verification (App\Services\USAssignmentService)
 * Test Cycle No. 1 - 5 test cases.
 *
 * The rotating attendance code is a 120-second TOTP derived from the
 * assignment's encrypted secret. Every check below is anchored to a fixed
 * Unix timestamp so the 120-second window boundaries are deterministic.
 *
 * ANCHOR sits 60 seconds inside the window that starts at 1 800 000 000,
 * so the window runs from 1 800 000 000 to 1 800 000 119 inclusive.
 */
class UsQrScanVerificationTest extends TestCase
{
    private const ANCHOR = 1800000060;

    private string $secret;

    private UsAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $generator = TOTP::create();
        $generator->setPeriod(120);
        $this->secret = $generator->getSecret();

        $this->assignment = new UsAssignment([
            'case_resolution_id' => 1,
            'student_id' => 42,
            'office_name' => 'Learning Resource Center',
            'total_hours_required' => 24,
            'hours_rendered' => 0,
            'status' => 'in_progress',
            'start_date' => '2026-08-01',
            'target_completion_date' => '2026-08-31',
            'totp_secret' => encrypt($this->secret),
        ]);
        $this->assignment->id = 7;
    }

    /** Scenario 1 - Normal: the scan payload identifies the assignment, the student and a 6-digit code. */
    public function test_the_scan_payload_identifies_the_assignment_student_and_code(): void
    {
        $payload = (new USAssignmentService)->generateTotpUri($this->assignment);

        $this->assertMatchesRegularExpression('/^PRISM_US:7:42:\d{6}$/', $payload);
    }

    /** Scenario 2 - Normal: the code issued for a moment verifies at that moment. */
    public function test_the_code_issued_for_a_moment_verifies_at_that_moment(): void
    {
        $code = $this->codeAt(self::ANCHOR);

        $this->assertTrue(
            (new USAssignmentService)->validateTotp($this->assignment, $code, self::ANCHOR)
        );
    }

    /** Scenario 3 - Failure: a code that was never issued is rejected. */
    public function test_a_code_that_was_never_issued_is_rejected(): void
    {
        $wrongCode = str_pad(
            (string) ((((int) $this->codeAt(self::ANCHOR)) + 1) % 1000000),
            6,
            '0',
            STR_PAD_LEFT
        );

        $this->assertFalse(
            (new USAssignmentService)->validateTotp($this->assignment, $wrongCode, self::ANCHOR)
        );
    }

    /** Scenario 4 - Boundary: a code stays valid for its own 120-second window and expires after it. */
    public function test_a_code_stays_valid_for_its_window_and_expires_after_it(): void
    {
        $service = new USAssignmentService;
        $code = $this->codeAt(self::ANCHOR);

        $this->assertTrue($service->validateTotp($this->assignment, $code, self::ANCHOR + 59));
        $this->assertFalse($service->validateTotp($this->assignment, $code, self::ANCHOR + 61));
    }

    /**
     * Scenario 5 - Boundary: a deferred offline scan is verified against the
     * client's recorded scan time, not the later upload time.
     */
    public function test_a_deferred_offline_scan_is_verified_against_the_recorded_scan_time(): void
    {
        $service = new USAssignmentService;
        $scannedAt = self::ANCHOR - 120;
        $code = $this->codeAt($scannedAt);

        $this->assertFalse($service->validateTotp($this->assignment, $code, self::ANCHOR));
        $this->assertTrue($service->validateTotp($this->assignment, $code, $scannedAt));
    }

    private function codeAt(int $timestamp): string
    {
        $totp = TOTP::createFromSecret($this->secret);
        $totp->setPeriod(120);

        return $totp->at($timestamp);
    }
}
