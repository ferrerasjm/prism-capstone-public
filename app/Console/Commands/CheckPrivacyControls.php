<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\IncidentAttachment;
use App\Models\UpccAppealAttachment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Reports on the privacy and security controls described in
 * docs/SECURITY_PRIVACY_CONTROLS.md — retention settings, attachment
 * scanning, access review and restore-test recency — warning where a
 * documented control is not actually active.
 */
class CheckPrivacyControls extends Command
{
    protected $signature = 'prism:privacy-check {--fail-on-warning : Return a failing exit code when warnings are found}';

    protected $description = 'Check PRISM security and privacy control readiness';

    public function handle(): int
    {
        $warnings = [];

        $this->info('PRISM security and privacy check');
        $this->checkApplicationSecurity($warnings);
        $this->checkRetention($warnings);
        $this->checkAttachments($warnings);
        $this->checkAccessReview($warnings);
        $this->checkBackups($warnings);

        if ($warnings === []) {
            $this->info('Security and privacy controls look good.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Warnings found:');

        foreach ($warnings as $warning) {
            $this->warn('- '.$warning);
        }

        return $this->option('fail-on-warning') ? self::FAILURE : self::SUCCESS;
    }

    private function checkApplicationSecurity(array &$warnings): void
    {
        $appUrl = (string) config('app.url');

        $this->line('Environment: '.app()->environment());
        $this->line('Debug mode: '.(config('app.debug') ? 'enabled' : 'disabled'));

        if (blank(config('app.key'))) {
            $warnings[] = 'APP_KEY is missing. Encrypted values and sessions are not safe until a key is configured.';
        }

        if (app()->environment('production')) {
            if (config('app.debug')) {
                $warnings[] = 'APP_DEBUG must be false in production.';
            }

            if (! Str::startsWith($appUrl, 'https://')) {
                $warnings[] = 'APP_URL should use HTTPS in production.';
            }
        }
    }

    private function checkRetention(array &$warnings): void
    {
        $auditYears = (int) config('prism_security.retention.audit_log_years');
        $cutoff = now()->subYears($auditYears);
        $olderAuditLogs = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->count();

        $this->line("Audit log retention: {$auditYears} year(s)");
        $this->line("Audit logs older than retention window: {$olderAuditLogs}");

        if ($olderAuditLogs > 0) {
            $warnings[] = "{$olderAuditLogs} audit log record(s) are older than the configured {$auditYears}-year retention window and should be reviewed before disposal.";
        }
    }

    private function checkAttachments(array &$warnings): void
    {
        $scanEnabled = (bool) config('prism_security.attachments.scan_uploads');
        $incidentWithoutHash = IncidentAttachment::query()
            ->whereNull('file_hash')
            ->count();
        $appealWithoutHash = UpccAppealAttachment::query()
            ->whereNull('file_hash')
            ->count();
        $missingHashTotal = $incidentWithoutHash + $appealWithoutHash;

        $this->line('Attachment malware scanning: '.($scanEnabled ? 'enabled' : 'disabled'));
        $this->line("Attachment records without file hash: {$missingHashTotal}");

        if (app()->environment('production') && ! $scanEnabled) {
            $warnings[] = 'Attachment malware scanning is disabled. Enable PRISM_SCAN_UPLOADS after installing a scanner such as ClamAV.';
        }

        if ($missingHashTotal > 0) {
            $warnings[] = "{$missingHashTotal} attachment record(s) do not have a stored SHA-256 hash. New uploads will be hashed automatically.";
        }
    }

    private function checkAccessReview(array &$warnings): void
    {
        $days = (int) config('prism_security.access_review.inactive_days');
        $cutoff = now()->subDays($days);
        $staffRoles = config('prism_security.access_review.staff_roles', []);

        $inactiveActiveStaff = User::query()
            ->whereIn('role', $staffRoles)
            ->where('status', 'active')
            ->whereDoesntHave('auditLogs', fn ($query) => $query
                ->where('module', 'auth')
                ->where('action', 'login')
                ->where('created_at', '>=', $cutoff))
            ->count();

        $this->line("Active staff accounts without login in {$days} day(s): {$inactiveActiveStaff}");

        if ($inactiveActiveStaff > 0) {
            $warnings[] = "{$inactiveActiveStaff} active staff account(s) have no recent login record and should be included in the next access review.";
        }
    }

    private function checkBackups(array &$warnings): void
    {
        $path = (string) config('prism_security.backup.path');
        $lastRestoreTestAt = config('prism_security.backup.last_restore_test_at');
        $intervalDays = (int) config('prism_security.backup.restore_test_interval_days');

        $this->line('Backup path: '.$path);
        $this->line('Last restore test: '.($lastRestoreTestAt ?: 'not recorded'));

        if (app()->environment('production') && ! File::isDirectory($path)) {
            $warnings[] = "Backup path '{$path}' does not exist.";
        }

        if (! $lastRestoreTestAt) {
            if (app()->environment('production')) {
                $warnings[] = 'No restore test date is configured. Set PRISM_LAST_RESTORE_TEST_AT after a successful restore drill.';
            }

            return;
        }

        try {
            $lastRestoreTest = CarbonImmutable::parse($lastRestoreTestAt);
        } catch (\Throwable) {
            $warnings[] = 'PRISM_LAST_RESTORE_TEST_AT is not a valid date.';

            return;
        }

        if ($lastRestoreTest->lt(now()->subDays($intervalDays))) {
            $warnings[] = "The last restore test is older than {$intervalDays} day(s). Run and document another restore drill.";
        }
    }
}
