<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SanctionMatrixSeeder extends Seeder
{
    /**
     * Initial University Service guidelines issued by the Student Discipline
     * Office. A single value is auto-recommended only when min and max match.
     * Variable guidelines remain ranges so the officer can select the
     * applicable subtype or severity instead of receiving a false exact value.
     *
     * The source document defines a first and second-offense guideline. Matrix
     * rows beyond the second offense therefore retain the second-offense hours.
     *
     * @var array<string, array<int, array{min: float, max: float, guidance: string}>>
     */
    private const OFFICIAL_US_GUIDELINES = [
        'MAJOR-01' => [
            1 => [
                'min' => 8.00,
                'max' => 40.00,
                'guidance' => 'Quizzes — unauthorized notes: 24h if used, 16h if unused; copying: 24h, allowing: 16h; glancing/allowing a glance: 8h; unauthorized communication/device use: 24h. Long tests/exams — unauthorized notes: 40h if used, 32h if unused; copying: 40h, allowing: 32h; glancing/allowing a glance: 24h; unauthorized communication/device use: 40h. Written reports/case analyses/experiments — plagiarism, leaking questions, or proxy exam/report/assignment: 40h.',
            ],
            2 => [
                'min' => 40.00,
                'max' => 200.00,
                'guidance' => 'Quizzes — unauthorized notes: 80h if used, 60h if unused; copying: 80h, allowing: 60h; glancing/allowing a glance: 40h; unauthorized communication/device use: 80h. Long tests/exams — unauthorized notes: 160h if used, 120h if unused; copying: 160h, allowing: 120h; glancing/allowing a glance: 80h; unauthorized communication/device use: 160h. Written reports/case analyses/experiments — plagiarism, leaking questions, or proxy exam/report/assignment: 200h.',
            ],
        ],
        'MAJOR-02' => [
            1 => [
                'min' => 40.00,
                'max' => 40.00,
                'guidance' => '40h only when the student’s appeal is approved and the offense is handled under Category 2.',
            ],
            2 => [
                'min' => 160.00,
                'max' => 160.00,
                'guidance' => 'Second-offense guideline: 160h.',
            ],
        ],
        'MAJOR-03' => [
            1 => [
                'min' => 32.00,
                'max' => 32.00,
                'guidance' => '32h only when the student’s appeal is approved and the offense is handled under Category 2.',
            ],
            2 => [
                'min' => 120.00,
                'max' => 120.00,
                'guidance' => 'Second-offense guideline: 120h.',
            ],
        ],
        'MAJOR-04' => [
            1 => ['min' => 16.00, 'max' => 16.00, 'guidance' => 'First-offense guideline: 16h.'],
            2 => ['min' => 60.00, 'max' => 60.00, 'guidance' => 'Second-offense guideline: 60h.'],
        ],
        'MAJOR-05' => [
            1 => [
                'min' => 24.00,
                'max' => 40.00,
                'guidance' => 'Unofficial document: 24h; official document: 40h.',
            ],
            2 => [
                'min' => 80.00,
                'max' => 200.00,
                'guidance' => 'Unofficial document: 80h; official document: 200h.',
            ],
        ],
        'MAJOR-07' => [
            1 => [
                'min' => 24.00,
                'max' => 32.00,
                'guidance' => 'Lending one’s University ID: 24h; using someone else’s University ID: 32h.',
            ],
            2 => [
                'min' => 80.00,
                'max' => 120.00,
                'guidance' => 'Lending one’s University ID: 80h; using someone else’s University ID: 120h.',
            ],
        ],
        'MAJOR-08' => [
            1 => [
                'min' => 8.00,
                'max' => 8.00,
                'guidance' => 'The initial misconduct incident is recorded as a minor violation; the next incident handled as this major offense carries 8h.',
            ],
            2 => [
                'min' => 40.00,
                'max' => 40.00,
                'guidance' => 'First repeat/habitual misconduct guideline: 40h.',
            ],
        ],
        'MAJOR-09' => [
            1 => [
                'min' => 8.00,
                'max' => 8.00,
                'guidance' => 'The initial disruption is recorded as a minor violation; the next incident handled as this major offense carries 8h.',
            ],
            2 => [
                'min' => 40.00,
                'max' => 40.00,
                'guidance' => 'First repeat/habitual misconduct guideline: 40h.',
            ],
        ],
        'MAJOR-15' => [
            1 => [
                'min' => 16.00,
                'max' => 40.00,
                'guidance' => 'Minor vandalism/unhygienic use: 16h; moderate damage requiring repair: 32h; severe destruction of property: 40h. Charge the student for applicable damages.',
            ],
            2 => [
                'min' => 40.00,
                'max' => 200.00,
                'guidance' => 'Minor vandalism/unhygienic use: 40h; moderate damage requiring repair: 120h; severe destruction of property: 200h. Charge the student for applicable damages.',
            ],
        ],
        'MAJOR-16' => [
            1 => [
                'min' => 24.00,
                'max' => 32.00,
                'guidance' => 'Moderate/isolated incident involving fewer copies: 24h; severe/large-scale prevention or removal: 32h.',
            ],
            2 => [
                'min' => 80.00,
                'max' => 120.00,
                'guidance' => 'Moderate/isolated incident involving fewer copies: 80h; severe/large-scale prevention or removal: 120h.',
            ],
        ],
        'MAJOR-17' => [
            1 => [
                'min' => 16.00,
                'max' => 40.00,
                'guidance' => 'Minor disrespect: 16h; moderate verbal confrontation/rude behavior/public ridicule: 24–32h; severe offensive, defamatory, or threatening conduct against school personnel: 40h.',
            ],
            2 => [
                'min' => 40.00,
                'max' => 200.00,
                'guidance' => 'Minor disrespect: 40h; moderate verbal confrontation/rude behavior/public ridicule: 80–120h; severe offensive, defamatory, or threatening conduct against school personnel: 200h.',
            ],
        ],
        'MAJOR-19' => [
            1 => [
                'min' => 8.00,
                'max' => 40.00,
                'guidance' => 'Three same-kind minor offenses — uniform/ID, slight attire, loitering, eating in restricted areas, littering, fixtures, or locker violations: 8h; moderate attire, inappropriate graphics, unauthorized facilities/equipment, concealed library material, hair color, gender-restricted area, or bypassing entrance: 16h; severe attire: 24h. Four to five delayed/overlooked minor offenses: 24–32h; four different kinds: 32–40h.',
            ],
            2 => [
                'min' => 40.00,
                'max' => 200.00,
                'guidance' => 'Repeated three same-kind minor offenses — uniform/ID, slight attire, loitering, eating in restricted areas, littering, fixtures, or locker violations: 40h; moderate attire, inappropriate graphics, unauthorized facilities/equipment, hair color: 64h; severe attire, concealed library material, gender-restricted area, or bypassing entrance: 80h. Repeated four to five delayed/overlooked offenses: 80–120h; repeated four different kinds: 160–200h.',
            ],
        ],
        'MAJOR-20' => [
            1 => ['min' => 16.00, 'max' => 16.00, 'guidance' => 'First-offense guideline: 16h.'],
            2 => ['min' => 40.00, 'max' => 40.00, 'guidance' => 'Second-offense guideline: 40h.'],
        ],
        'MAJOR-21' => [
            1 => [
                'min' => 24.00,
                'max' => 24.00,
                'guidance' => 'Intentional entry, defiance, or related misconduct: 24h. If the student was unaware that the area was restricted and had no malice, issue a warning and record a minor violation instead.',
            ],
            2 => [
                'min' => 40.00,
                'max' => 80.00,
                'guidance' => 'Repeat entry without malice: 40h; intentional or defiant entry: 80h.',
            ],
        ],
        'MAJOR-22' => [
            1 => [
                'min' => 24.00,
                'max' => 40.00,
                'guidance' => 'Moderate dormitory violation such as noise or curfew: 24h; severe violation involving property damage, harassment, or prohibited substances: 40h.',
            ],
            2 => [
                'min' => 80.00,
                'max' => 160.00,
                'guidance' => 'Moderate dormitory violation such as noise or curfew: 80h; severe violation involving property damage, harassment, or prohibited substances: 160h.',
            ],
        ],
        'MAJOR-23' => [
            1 => [
                'min' => 40.00,
                'max' => 40.00,
                'guidance' => '40h only when a valid plea is approved by the Discipline Office and the offense is reduced to Category 2.',
            ],
        ],
        'MAJOR-24' => [
            1 => ['min' => 40.00, 'max' => 40.00, 'guidance' => 'First-offense guideline: 40h.'],
            2 => ['min' => 160.00, 'max' => 160.00, 'guidance' => 'Second-offense guideline: 160h.'],
        ],
        'MAJOR-26' => [
            1 => [
                'min' => 16.00,
                'max' => 40.00,
                'guidance' => 'Bringing the item without using it: 16h; Smoking/using it: 40h. Confiscate the item.',
            ],
            2 => [
                'min' => 40.00,
                'max' => 160.00,
                'guidance' => 'Bringing the item without using it: 40h; Smoking/using it: 160h. Confiscate the item.',
            ],
        ],
        'MAJOR-28' => [
            1 => ['min' => 24.00, 'max' => 24.00, 'guidance' => 'First-offense guideline: 24h.'],
            2 => ['min' => 80.00, 'max' => 80.00, 'guidance' => 'Second-offense guideline: 80h.'],
        ],
        'MAJOR-29' => [
            1 => ['min' => 32.00, 'max' => 32.00, 'guidance' => 'First-offense guideline: 32h.'],
            2 => ['min' => 160.00, 'max' => 160.00, 'guidance' => 'Second-offense guideline: 160h.'],
        ],
        'MAJOR-30' => [
            1 => ['min' => 40.00, 'max' => 40.00, 'guidance' => 'First-offense guideline: 40h.'],
            2 => ['min' => 200.00, 'max' => 200.00, 'guidance' => 'Second-offense guideline: 200h.'],
        ],
        'MAJOR-39' => [
            1 => ['min' => 32.00, 'max' => 32.00, 'guidance' => 'First-offense guideline: 32h.'],
            2 => ['min' => 160.00, 'max' => 160.00, 'guidance' => 'Second-offense guideline: 160h.'],
        ],
        'MAJOR-45' => [
            1 => [
                'min' => 8.00,
                'max' => 40.00,
                'guidance' => 'Minor violation such as eating/drinking, minor misuse, or failure to log in/out: 8h; moderate violation such as unauthorized software, restricted-site access, or repeated misuse: 16h; severe intentional damage, hacking, or system compromise: 40h. Charge applicable damages.',
            ],
            2 => [
                'min' => 40.00,
                'max' => 200.00,
                'guidance' => 'Minor violation: 40h; moderate violation: 80h; severe intentional damage, hacking, or system compromise: 160–200h. Charge applicable damages.',
            ],
        ],
    ];

    public function run(): void
    {
        // Preserve policy changes made through Offense & Policy Management.
        if (DB::table('sanction_matrix')->exists()) {
            return;
        }

        $matrix = [
            ['MAJOR-01', 1, 2, 'Grade of 0.0 in the output (quizzes, written reports, exams, etc.)'],
            ['MAJOR-01', 2, 2, 'Grade of 0.0 in the course or subject'],
            ['MAJOR-01', 3, 3, 'Grade of 0.0 in the course'],

            ['MAJOR-02', 1, 2, null],
            ['MAJOR-02', 2, 2, null],

            ['MAJOR-03', 1, 2, null],
            ['MAJOR-03', 2, 2, null],

            ['MAJOR-04', 1, 2, null],
            ['MAJOR-04', 2, 2, null],
            ['MAJOR-04', 3, 3, null],

            ['MAJOR-05', 1, 3, null],
            ['MAJOR-05', 2, 3, null],

            ['MAJOR-06', 1, 3, null],

            ['MAJOR-07', 1, 2, null],
            ['MAJOR-07', 2, 2, null],
            ['MAJOR-07', 3, 3, null],

            ['MAJOR-08', 1, 2, null],
            ['MAJOR-08', 2, 2, null],
            ['MAJOR-08', 3, 3, null],

            ['MAJOR-09', 1, 2, null],
            ['MAJOR-09', 2, 2, null],
            ['MAJOR-09', 3, 3, null],

            ['MAJOR-10', 1, 3, null],

            ['MAJOR-11', 1, 3, null],

            ['MAJOR-12', 1, 3, null],

            ['MAJOR-13', 1, 3, null],

            ['MAJOR-14', 1, 4, null],

            ['MAJOR-15', 1, 2, 'Student also charged for the damages'],
            ['MAJOR-15', 2, 2, 'Student also charged for the damages'],
            ['MAJOR-15', 3, 3, 'Student also charged for the damages'],

            ['MAJOR-16', 1, 2, null],
            ['MAJOR-16', 2, 2, null],
            ['MAJOR-16', 3, 3, null],

            ['MAJOR-17', 1, 2, null],
            ['MAJOR-17', 2, 2, null],
            ['MAJOR-17', 3, 3, null],

            ['MAJOR-18', 1, 3, null],

            ['MAJOR-19', 1, 2, null],
            ['MAJOR-19', 2, 2, null],
            ['MAJOR-19', 3, 3, null],

            ['MAJOR-20', 1, 2, null],
            ['MAJOR-20', 2, 2, null],
            ['MAJOR-20', 3, 3, null],

            ['MAJOR-21', 1, 2, null],
            ['MAJOR-21', 2, 2, null],
            ['MAJOR-21', 3, 3, null],

            ['MAJOR-22', 1, 2, null],
            ['MAJOR-22', 2, 2, null],
            ['MAJOR-22', 3, 3, null],

            ['MAJOR-23', 1, 3, null],

            ['MAJOR-24', 1, 2, null],
            ['MAJOR-24', 2, 3, null],

            ['MAJOR-25', 1, 3, 'Item confiscated'],

            ['MAJOR-26', 1, 2, null],
            ['MAJOR-26', 2, 2, 'Bringing in: Category 2 + item confiscation. Using/Smoking: Category 3.'],
            ['MAJOR-26', 3, 3, 'Category 3 + item confiscation'],

            ['MAJOR-27', 1, 3, null],

            ['MAJOR-28', 1, 2, null],
            ['MAJOR-28', 2, 2, null],
            ['MAJOR-28', 3, 3, null],

            ['MAJOR-29', 1, 2, null],
            ['MAJOR-29', 2, 2, null],
            ['MAJOR-29', 3, 3, null],

            ['MAJOR-30', 1, 2, null],
            ['MAJOR-30', 2, 2, null],
            ['MAJOR-30', 3, 3, null],

            ['MAJOR-31', 1, 4, null],
            ['MAJOR-32', 1, 4, null],
            ['MAJOR-33', 1, 3, null],
            ['MAJOR-34', 1, 3, null],
            ['MAJOR-35', 1, 3, null],

            ['MAJOR-36', 1, 2, 'Formative Intervention: referral for counseling and related seminar.'],
            ['MAJOR-36', 2, 3, 'Formative Intervention: referral for counseling and related seminar.'],
            ['MAJOR-36', 3, 3, 'Formative Intervention: referral for counseling and related seminar.'],

            ['MAJOR-37', 1, 2, 'Formative Intervention: referral for counseling and related seminar.'],
            ['MAJOR-37', 2, 3, 'Formative Intervention: referral for counseling and related seminar.'],
            ['MAJOR-37', 3, 3, 'Formative Intervention: referral for counseling and related seminar.'],

            ['MAJOR-38', 1, 4, null],

            ['MAJOR-39', 1, 2, null],
            ['MAJOR-39', 2, 2, null],
            ['MAJOR-39', 3, 3, null],

            ['MAJOR-40', 1, 2, 'Formative Intervention: referral for counseling and related seminar.'],
            ['MAJOR-40', 2, 3, 'Formative Intervention: referral for counseling and related seminar.'],
            ['MAJOR-40', 3, 3, 'Formative Intervention: referral for counseling and related seminar.'],

            ['MAJOR-41', 1, 2, 'Formative Intervention: referral for counseling and related seminar, regardless of 1st or 2nd offense.'],
            ['MAJOR-41', 2, 2, 'Formative Intervention: referral for counseling and related seminar, regardless of 1st or 2nd offense.'],

            ['MAJOR-42', 1, 3, null],
            ['MAJOR-42', 2, 4, null],

            ['MAJOR-43', 1, 3, null],

            ['MAJOR-44', 1, 1, null],
            ['MAJOR-44', 2, 2, null],
            ['MAJOR-44', 3, 3, null],

            ['MAJOR-45', 1, 2, null],
            ['MAJOR-45', 2, 2, null],
            ['MAJOR-45', 3, 3, null],

            ['MAJOR-46', 1, 3, null],
            ['MAJOR-47', 1, 3, null],
        ];

        $inserted = 0;
        $skipped = 0;

        foreach ($matrix as [$code, $count, $category, $notes]) {
            $offense = DB::table('offense_catalog')->where('code', $code)->first();

            if (! $offense) {
                $this->command->warn("  Offense not found: {$code} — skipped.");
                $skipped++;

                continue;
            }

            $guideline = self::guidelineFor($code, $count);
            $usHours = $guideline && $guideline['min'] === $guideline['max']
                ? $guideline['min']
                : null;

            DB::table('sanction_matrix')->insert([
                'offense_catalog_id' => $offense->id,
                'offense_count' => $count,
                'sanction_category' => $category,
                'us_hours_recommended' => $usHours,
                'us_hours_min' => $guideline['min'] ?? null,
                'us_hours_max' => $guideline['max'] ?? null,
                'us_hours_guidance' => $guideline['guidance'] ?? null,
                'notes' => $notes,
            ]);

            $inserted++;
        }

        $this->command->info("✓ Sanction matrix seeded: {$inserted} rows across 47 major offenses.");

        if ($skipped > 0) {
            $this->command->warn("  {$skipped} rows skipped — offense code not found in catalog.");
        }

        $this->command->newLine();
        $this->command->info('Official SDO University Service hour guidelines applied.');
        $this->command->info('Exact guidelines are auto-recommended; subtype/severity-based guidelines are stored as ranges for officer selection.');
    }

    /**
     * @return array{min: float, max: float, guidance: string}|null
     */
    public static function guidelineFor(string $code, int $offenseCount): ?array
    {
        $guidelines = self::OFFICIAL_US_GUIDELINES[$code] ?? null;

        if (! $guidelines) {
            return null;
        }

        if (isset($guidelines[$offenseCount])) {
            return $guidelines[$offenseCount];
        }

        return $offenseCount > 2
            ? ($guidelines[2] ?? null)
            : null;
    }
}
