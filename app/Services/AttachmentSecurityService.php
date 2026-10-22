<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Inspects an uploaded evidence file before it is stored: computes its
 * hash for the audit trail, and malware-scans it when
 * PRISM_SCAN_UPLOADS is enabled and a scanner (e.g. ClamAV) is installed.
 * Scanning is opt-in — see docs/SECURITY_PRIVACY_CONTROLS.md.
 */
class AttachmentSecurityService
{
    public function inspect(UploadedFile $file): array
    {
        $metadata = [
            'file_hash' => $this->hash($file),
            'scan_status' => 'not_configured',
            'scanned_at' => null,
        ];

        if (! config('prism_security.attachments.scan_uploads')) {
            return $metadata;
        }

        $binary = trim((string) config('prism_security.attachments.scanner_binary'));

        if ($binary === '') {
            throw ValidationException::withMessages([
                'attachments' => 'Attachment security scanning is enabled but no scanner binary is configured.',
            ]);
        }

        $process = new Process([
            $binary,
            '--no-summary',
            $file->getRealPath(),
        ]);

        $process->setTimeout((int) config('prism_security.attachments.scanner_timeout_seconds'));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw ValidationException::withMessages([
                'attachments' => 'Attachment security scanning timed out. Please try again or contact the Discipline Office.',
            ]);
        }

        if ($process->isSuccessful()) {
            return [
                ...$metadata,
                'scan_status' => 'clean',
                'scanned_at' => now(),
            ];
        }

        if ($process->getExitCode() === 1) {
            throw ValidationException::withMessages([
                'attachments' => 'One uploaded attachment did not pass the security scan.',
            ]);
        }

        throw ValidationException::withMessages([
            'attachments' => 'Attachment security scanning is currently unavailable. Please try again later.',
        ]);
    }

    private function hash(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        if (! $path || ! is_file($path)) {
            return null;
        }

        return hash_file('sha256', $path) ?: null;
    }
}
