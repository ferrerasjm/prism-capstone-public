<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/** Verifies the exact UTF-8 JSON bytes signed at capture, never reserialized JSON. */
class AttendanceProof
{
    public const MAX_ANCHOR_AGE_MS = 86400000;

    public function issue(string $publicKey, string $userId, string $nonce): array
    {
        $key = openssl_pkey_get_public($publicKey);
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (! $details || ($details['ec']['curve_name'] ?? '') !== 'prime256v1') {
            throw ValidationException::withMessages(['public_key' => 'An EC P-256 public key is required.']);
        }
        $stamp = (int) now()->getTimestampMs();
        $anchor = ['version' => 1, 'user_id' => $userId, 'public_key' => $publicKey,
            'nonce' => $nonce, 'server_ms' => $stamp, 'max_age_ms' => self::MAX_ANCHOR_AGE_MS];

        return ['anchor' => Crypt::encryptString(json_encode($anchor, JSON_THROW_ON_ERROR)),
            'server_ms' => $stamp, 'nonce' => $nonce, 'max_age_ms' => self::MAX_ANCHOR_AGE_MS];
    }

    public function verify(string $payload, string $signature, string $userId, string $assignmentId): array
    {
        try {
            $event = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
            $anchor = json_decode(Crypt::decryptString($event['anchor']), true, 16, JSON_THROW_ON_ERROR);
            $sig = base64_decode($signature, true);
            if ($sig === false || openssl_verify($payload, $sig, $anchor['public_key'], OPENSSL_ALGO_SHA256) !== 1) {
                throw new \RuntimeException('Signature mismatch');
            }
            if (($event['version'] ?? null) !== 1 || ($anchor['version'] ?? null) !== 1
                || $anchor['user_id'] !== $userId || $event['user_id'] !== $userId
                || $event['assignment_id'] !== $assignmentId) {
                throw new \RuntimeException('Context mismatch');
            }
            $elapsed = $event['elapsed_ms'];
            $uncertainty = $event['uncertainty_ms'];
            if (! is_int($elapsed) || $elapsed < 0 || $elapsed > $anchor['max_age_ms']
                || ! is_int($uncertainty) || $uncertainty < 0 || $uncertainty > 10000
                || ! is_int($event['captured_at_ms']) || ! is_int($event['wall_clock_ms'])) {
                throw new \RuntimeException('Invalid time evidence');
            }
            $expected = $anchor['server_ms'] + $elapsed;
            // This is the exact 120-second clock-discrepancy rule, not upload age.
            if ($event['captured_at_ms'] !== $expected
                || abs($event['wall_clock_ms'] - $expected) + $uncertainty > 120000
                || $expected > now()->getTimestampMs() + $uncertainty) {
                throw new \RuntimeException('Scan time outside tolerance');
            }

            return $event;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['attendance_proof' => 'Invalid attendance signature or scan-time evidence.']);
        }
    }
}
