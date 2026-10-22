<?php

namespace Tests\Feature;

use App\Models\UniversityServiceOffice;
use App\Models\UsAssignment;
use App\Models\User;
use App\Services\AttendanceProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OTPHP\TOTP;
use Tests\Support\AttendanceSigningKey;
use Tests\TestCase;

class MobileAttendanceProofTest extends TestCase
{
    use RefreshDatabase;

    private function keys(): array
    {
        return AttendanceSigningKey::generate();
    }

    private function event(array $anchor, string $userId, string $assignmentId, int $elapsed = 0): array
    {
        $totp = TOTP::createFromSecret('JBSWY3DPEHPK3PXP');
        $totp->setPeriod(120);
        $stamp = $anchor['server_ms'] + $elapsed;

        return ['version' => 1, 'anchor' => $anchor['anchor'], 'user_id' => $userId,
            'assignment_id' => $assignmentId, 'elapsed_ms' => $elapsed,
            'uncertainty_ms' => 0, 'captured_at_ms' => $stamp, 'wall_clock_ms' => $stamp,
            'client_log_id' => (string) Str::uuid(), 'event_type' => 'time_in',
            'totp' => $totp->at(intdiv($stamp, 1000)), 'tasks_performed' => 'Library work', 'override_reason' => null];
    }

    private function sign(array $event, $key): array
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256);

        return ['signed_payload' => $payload, 'signature' => base64_encode($signature)];
    }

    public function test_exact_clock_tolerance_and_delayed_verification(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$key, $public] = $this->keys();
        $service = app(AttendanceProof::class);
        $anchor = $service->issue($public, '1', 'nonce');
        $event = $this->event($anchor, '1', '9');
        $event['wall_clock_ms'] += 120000;
        $signed = $this->sign($event, $key);
        $this->travel(6)->hours();
        $this->assertSame($event, $service->verify($signed['signed_payload'], $signed['signature'], '1', '9'));
        $event['wall_clock_ms']++;
        $signed = $this->sign($event, $key);
        $this->expectException(ValidationException::class);
        $service->verify($signed['signed_payload'], $signed['signature'], '1', '9');
    }

    public function test_changed_signed_data_is_rejected(): void
    {
        [$key, $public] = $this->keys();
        $service = app(AttendanceProof::class);
        $event = $this->event($service->issue($public, '1', 'nonce'), '1', '9');
        $signed = $this->sign($event, $key);
        $event['event_type'] = 'time_out';
        $this->expectException(ValidationException::class);
        $service->verify(json_encode($event), $signed['signature'], '1', '9');
    }

    public function test_anchor_cannot_be_used_for_new_captures_after_24_hours(): void
    {
        [$key, $public] = $this->keys();
        $service = app(AttendanceProof::class);
        $anchor = $service->issue($public, '1', 'nonce');
        $event = $this->event($anchor, '1', '9', 86400001);
        $this->travel(2)->days();
        $signed = $this->sign($event, $key);
        $this->expectException(ValidationException::class);
        $service->verify($signed['signed_payload'], $signed['signature'], '1', '9');
    }

    public function test_handshake_uncertainty_counts_toward_tolerance(): void
    {
        [$key, $public] = $this->keys();
        $service = app(AttendanceProof::class);
        $event = $this->event($service->issue($public, '1', 'nonce'), '1', '9');
        $event['wall_clock_ms'] -= 120000;
        $event['uncertainty_ms'] = 1;
        $signed = $this->sign($event, $key);
        $this->expectException(ValidationException::class);
        $service->verify($signed['signed_payload'], $signed['signature'], '1', '9');
    }

    public function test_fake_server_anchor_is_rejected_even_with_valid_device_signature(): void
    {
        [$key, $public] = $this->keys();
        $service = app(AttendanceProof::class);
        $event = $this->event($service->issue($public, '1', 'nonce'), '1', '9');
        $event['anchor'] = 'forged-anchor';
        $signed = $this->sign($event, $key);
        $this->expectException(ValidationException::class);
        $service->verify($signed['signed_payload'], $signed['signature'], '1', '9');
    }

    public function test_other_account_cannot_use_the_anchor(): void
    {
        [$key, $public] = $this->keys();
        $service = app(AttendanceProof::class);
        $event = $this->event($service->issue($public, '1', 'nonce'), '1', '9');
        $signed = $this->sign($event, $key);
        $this->expectException(ValidationException::class);
        $service->verify($signed['signed_payload'], $signed['signature'], '2', '9');
    }

    public function test_offline_pair_syncs_hours_later_once_and_replay_is_rejected(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$assignment, $user] = $this->fixture();
        $this->actingAs($user, 'api');
        [$key, $public] = $this->keys();
        $anchor = $this->postJson('/api/mobile/us/time-anchor', [
            'public_key' => $public, 'nonce' => (string) Str::uuid(),
        ])->assertOk()->json();
        $in = $this->event($anchor, (string) $user->id, (string) $assignment->id);
        $out = $this->event($anchor, (string) $user->id, (string) $assignment->id, 3600000);
        $out['event_type'] = 'time_out';
        $this->travel(6)->hours();
        $url = "/api/mobile/us/assignments/{$assignment->id}/log";
        $signedIn = $this->sign($in, $key);
        $signedOut = $this->sign($out, $key);
        $this->postJson($url, $signedIn)->assertCreated();
        $this->postJson($url, $signedOut)->assertCreated()->assertJsonPath('hoursRendered', 1);
        $this->postJson($url, $signedIn)->assertOk();
        $this->postJson($url, $signedOut)->assertOk();
        $this->assertDatabaseCount('mobile_attendance_receipts', 2);
        $this->assertDatabaseCount('us_attendance_logs', 1);
        $this->assertEquals(1, $assignment->fresh()->hours_rendered);
        $in['client_log_id'] = (string) Str::uuid();
        $this->postJson($url, $this->sign($in, $key))->assertStatus(409)->assertJsonPath('error_code', 'attendance_replay');
        $out['tasks_performed'] = 'Changed tasks';
        $this->postJson($url, $this->sign($out, $key))->assertStatus(409)->assertJsonPath('error_code', 'attendance_replay');
    }

    public function test_unsigned_legacy_event_is_not_silently_accepted(): void
    {
        [$assignment, $user] = $this->fixture();
        $this->actingAs($user, 'api')->postJson("/api/mobile/us/assignments/{$assignment->id}/log", [
            'event_type' => 'time_in', 'occurred_at' => now()->toIso8601String(), 'offline' => true,
        ])->assertStatus(422)->assertJsonPath('error_code', 'attendance_proof_required');
        $this->assertDatabaseCount('us_attendance_logs', 0);
    }

    public function test_missing_totp_is_reported_only_after_signature_verification(): void
    {
        [$assignment, $user] = $this->fixture();
        $this->actingAs($user, 'api');
        [$key, $public] = $this->keys();
        $anchor = app(AttendanceProof::class)->issue($public, (string) $user->id, 'nonce');
        $event = $this->event($anchor, (string) $user->id, (string) $assignment->id);
        $event['totp'] = null;
        $signed = $this->sign($event, $key);
        $url = "/api/mobile/us/assignments/{$assignment->id}/log";
        $this->postJson($url, $signed)->assertStatus(422)->assertJsonPath('error_code', 'totp_required');

        // Still a well-formed signature, but from a different key.
        [$otherKey] = $this->keys();
        $forged = $this->sign($event, $otherKey);
        $this->postJson($url, $forged)->assertStatus(422)->assertJsonPath('error_code', 'invalid_attendance_proof');
        $this->assertDatabaseCount('us_attendance_logs', 0);
        $this->assertDatabaseCount('mobile_attendance_receipts', 0);
    }

    private function fixture(): array
    {
        UniversityServiceOffice::firstOrCreate(['name' => 'Library'], ['is_active' => true]);
        $user = User::create(['first_name' => 'Proof', 'last_name' => 'Supervisor',
            'email' => 'proof@nu-clark.edu.ph', 'institutional_id' => 'PROOF-1',
            'password' => 'unused', 'role' => 'us_supervisor', 'office_name' => 'Library', 'status' => 'active']);
        $student = User::create(['first_name' => 'Proof', 'last_name' => 'Student',
            'email' => 'proof-student@nu-clark.edu.ph', 'institutional_id' => 'PROOF-2',
            'password' => 'unused', 'role' => 'student', 'status' => 'active']);
        $assignment = UsAssignment::create(['student_id' => $student->id, 'supervisor_id' => $user->id,
            'assigned_by' => $user->id, 'office_name' => 'Library', 'total_hours_required' => 8,
            'hours_rendered' => 0, 'start_date' => today()->subWeek(), 'target_completion_date' => today()->addWeek(),
            'status' => 'in_progress', 'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'totp_secret_generated_at' => now()]);

        return [$assignment, $user];
    }
}
