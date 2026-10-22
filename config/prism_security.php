<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Retention controls
    |--------------------------------------------------------------------------
    |
    | These values document the system retention baseline. PRISM does not
    | automatically delete official disciplinary records unless the Discipline
    | Office authorizes a disposal run.
    |
    */

    'retention' => [
        'case_record_years' => (int) env('PRISM_CASE_RECORD_RETENTION_YEARS', 7),
        'attachment_years' => (int) env('PRISM_ATTACHMENT_RETENTION_YEARS', 7),
        'generated_report_years' => (int) env('PRISM_REPORT_RETENTION_YEARS', 5),
        'audit_log_years' => (int) env('PRISM_AUDIT_LOG_RETENTION_YEARS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachment scanning
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'scan_uploads' => (bool) env('PRISM_SCAN_UPLOADS', false),
        'scanner_binary' => env('PRISM_MALWARE_SCANNER_BINARY', 'clamscan'),
        'scanner_timeout_seconds' => (int) env('PRISM_MALWARE_SCANNER_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Access review controls
    |--------------------------------------------------------------------------
    */

    'access_review' => [
        'inactive_days' => (int) env('PRISM_ACCESS_REVIEW_INACTIVE_DAYS', 90),
        'staff_roles' => [
            'super_admin',
            'discipline_coordinator',
            'discipline_officer',
            'guidance_officer',
            'reporting_user',
            'us_supervisor',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup and restore controls
    |--------------------------------------------------------------------------
    */

    'backup' => [
        'path' => env('PRISM_BACKUP_PATH', storage_path('app/backups')),
        'restore_test_interval_days' => (int) env('PRISM_RESTORE_TEST_INTERVAL_DAYS', 90),
        'last_restore_test_at' => env('PRISM_LAST_RESTORE_TEST_AT'),
    ],
];
