<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CsvImportLog;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bulk student CSV import. Validates each row, skips duplicates by student
 * number and email, creates the user plus profile in a transaction, and
 * queues one activation email per successfully created account.
 */
class StudentImportService
{
    public const CSV_HEADERS = [
        'student_number',
        'last_name',
        'first_name',
        'email',
        'middle_name',
    ];

    private const DOMAIN = '@students.nu-clark.edu.ph';

    public function __construct(
        private readonly AccountSetupService $accountSetup,
        private readonly AnnouncementService $announcements,
    ) {}

    /**
     * Imports a student CSV, one account per valid row.
     *
     * Returns early with ['empty' => true] when the file has no data
     * rows at all, rather than writing a batch record: a zero-row
     * import is not worth keeping and pushes real imports down the
     * history. Duplicate student numbers and emails are skipped rather
     * than failing the whole file, so one bad row cannot cost the rest
     * of the import. Each created account is written inside its own
     * transaction and queues one activation email.
     */
    public function import(UploadedFile $file, User $importedBy): array
    {
        $batchId = (string) Str::uuid();
        $rows = $this->parseCsv($file);

        /*
         * Nothing to import - either the file had no header row at all, or headers with no
         * students underneath. Bail out before writing anything: a batch that imported zero
         * rows is not a record worth keeping, and it pushed real imports down the history.
         */
        if ($rows === []) {
            return ['empty' => true];
        }

        $created = 0;
        $skipped = 0;
        $errors = 0;
        $activationEmailsQueued = 0;
        $activationEmailFailures = 0;
        $results = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $validation = $this->validateRow($row, $rowNumber);

            if ($validation !== null) {
                $errors++;
                $results[] = [
                    'row' => $rowNumber,
                    'student_number' => trim($row['student_number'] ?? ''),
                    'name' => null,
                    'email' => strtolower(trim($row['email'] ?? '')),
                    'outcome' => 'error',
                    'activation_email' => 'not_sent',
                    'reason' => $validation,
                ];

                continue;
            }

            $studentNumber = trim($row['student_number']);
            $lastName = trim($row['last_name']);
            $firstName = trim($row['first_name']);
            $middleName = isset($row['middle_name']) ? trim($row['middle_name']) : null;
            $middleName = $middleName === '' ? null : $middleName;
            $email = strtolower(trim($row['email']));
            $listName = User::formatListName($lastName, $firstName, $middleName);

            if (StudentProfile::where('student_number', $studentNumber)->exists()) {
                $skipped++;
                $results[] = [
                    'row' => $rowNumber,
                    'student_number' => $studentNumber,
                    'name' => $listName,
                    'email' => $email,
                    'outcome' => 'skipped',
                    'activation_email' => 'not_sent',
                    'reason' => 'Student number already exists in the system.',
                ];

                continue;
            }

            if (User::where('email', $email)->exists()) {
                $skipped++;
                $results[] = [
                    'row' => $rowNumber,
                    'student_number' => $studentNumber,
                    'name' => $listName,
                    'email' => $email,
                    'outcome' => 'skipped',
                    'activation_email' => 'not_sent',
                    'reason' => 'Email address already exists in the system.',
                ];

                continue;
            }

            $user = DB::transaction(function () use (
                $studentNumber,
                $lastName,
                $firstName,
                $middleName,
                $email,
                $importedBy,
            ): User {
                $user = User::create([
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'institutional_id' => $studentNumber,
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'role' => 'student',
                    'status' => 'pending_setup',
                    'created_by' => $importedBy->id,
                ]);

                StudentProfile::create([
                    'user_id' => $user->id,
                    'student_number' => $studentNumber,
                ]);

                return $user;
            });

            $activationEmail = 'queued';
            $reason = null;

            try {
                $this->accountSetup->issue($user, $importedBy->id);
                $activationEmailsQueued++;
            } catch (Throwable $exception) {
                report($exception);
                $activationEmail = 'queue_failed';
                $activationEmailFailures++;
                $reason = 'Account created, but the activation email could not be queued. Resend it from User Management.';
            }

            $this->announcements->publishToUser(
                $user,
                $importedBy,
                'Your account was created',
                'The System Administrator created your PRISM account through the student import.',
            );

            $created++;
            $results[] = [
                'row' => $rowNumber,
                'student_number' => $studentNumber,
                'name' => $listName,
                'email' => $email,
                'outcome' => 'created',
                'activation_email' => $activationEmail,
                'reason' => $reason,
            ];
        }

        CsvImportLog::create([
            'import_batch_id' => $batchId,
            'imported_by' => $importedBy->id,
            'file_name' => $file->getClientOriginalName(),
            'total_rows' => count($rows),
            'created_count' => $created,
            'skipped_count' => $skipped,
            'error_count' => $errors,
            'results_json' => $results,
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $importedBy->id,
            'action' => 'import',
            'module' => 'users',
            'new_values' => [
                'batch_id' => $batchId,
                'file' => $file->getClientOriginalName(),
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
                'activation_emails_queued' => $activationEmailsQueued,
                'activation_email_failures' => $activationEmailFailures,
            ],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return [
            'batch_id' => $batchId,
            'total' => count($rows),
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'activation_emails_queued' => $activationEmailsQueued,
            'activation_email_failures' => $activationEmailFailures,
            'rows' => $results,
        ];
    }

    /** Parse a CSV into associative rows keyed by its own header row. */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $rows = [];

        $headers = fgetcsv($handle);
        if ($headers === false) {
            return [];
        }

        $headers = array_map(
            fn ($header) => strtolower(trim(str_replace(' ', '_', $header))),
            $headers,
        );

        while (($data = fgetcsv($handle)) !== false) {
            // fgetcsv() returns [null] for a genuinely blank line - not [''] - and trim()
            // rejects null under strict_types, so a stray blank line would 500 the import.
            if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($data, count($headers), ''));
        }

        fclose($handle);

        return $rows;
    }

    /*
     * A 4-digit year, a dash, then digits and nothing else. The sequence length itself is
     * deliberately unbounded - it was 6 digits, is 7 now, and pinning a specific width would
     * only break again the next time it grows. This still catches what actually broke: a
     * leading "#" from a stale template's comment lines, or any other non-numeric junk.
     */
    private const STUDENT_NUMBER_PATTERN = '/^\d{4}-\d+$/';

    /*
     * Person-name fields. Letters of any script plus the punctuation real names
     * use - space, hyphen, apostrophe (straight and curly), and period for
     * initials. Must start with a letter. Keeps HTML/script punctuation out of
     * names as a second layer behind output escaping. Mirrors NAME_RULES in
     * Admin\UserController.
     */
    private const NAME_PATTERN = '/^\p{L}[\p{L}\p{M}\x27\x{2019}.\- ]*$/u';

    private const NAME_MAX_LENGTH = 150;

    private function validateRow(array $row, int $rowNumber): ?string
    {
        $studentNumber = trim($row['student_number'] ?? '');
        $lastName = trim($row['last_name'] ?? '');
        $firstName = trim($row['first_name'] ?? '');
        $middleName = trim($row['middle_name'] ?? '');
        $email = strtolower(trim($row['email'] ?? ''));

        if ($studentNumber === '') {
            return "Row {$rowNumber}: Student number is required.";
        }

        if (! preg_match(self::STUDENT_NUMBER_PATTERN, $studentNumber)) {
            return "Row {$rowNumber}: Student number must look like 2020-0000001 (year, dash, "
                ."digits only) - got \"{$studentNumber}\".";
        }

        if ($lastName === '') {
            return "Row {$rowNumber}: Last name is required.";
        }

        if ($firstName === '') {
            return "Row {$rowNumber}: First name is required.";
        }

        foreach (['Last name' => $lastName, 'First name' => $firstName, 'Middle name' => $middleName] as $label => $value) {
            if ($value === '') {
                continue;
            }

            if (mb_strlen($value) > self::NAME_MAX_LENGTH) {
                return "Row {$rowNumber}: {$label} may not exceed ".self::NAME_MAX_LENGTH.' characters.';
            }

            if (! preg_match(self::NAME_PATTERN, $value)) {
                return "Row {$rowNumber}: {$label} may use letters, spaces, hyphens, apostrophes, "
                    ."and periods only - got \"{$value}\".";
            }
        }

        if ($email === '') {
            return "Row {$rowNumber}: Official student email is required.";
        }

        if (strlen($email) > 255) {
            return "Row {$rowNumber}: Official student email may not exceed 255 characters.";
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return "Row {$rowNumber}: Official student email is invalid.";
        }

        if (! str_ends_with($email, self::DOMAIN)) {
            return "Row {$rowNumber}: Email must use the ".self::DOMAIN.' domain.';
        }

        return null;
    }
}
