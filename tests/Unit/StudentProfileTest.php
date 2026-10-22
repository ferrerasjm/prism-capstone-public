<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\StudentProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StudentProfileTest extends TestCase
{
    #[DataProvider('programAbbreviations')]
    public function test_it_formats_program_names_for_compact_displays(?string $program, string $expected): void
    {
        $this->assertSame($expected, StudentProfile::abbreviatedProgramName($program));
    }

    public static function programAbbreviations(): array
    {
        return [
            ['BS Information Technology', 'BSIT'],
            ['BS Computer Engineering', 'BSCpE'],
            ['BS Civil Engineering', 'BSCE'],
            ['BS Accountancy', 'BSA'],
            ['BSBA Marketing Management', 'BSBA-MM'],
            ['BS Tourism Management', 'BSTM'],
            ['Engineering and Architecture (STEM_EA)', 'STEM_EA'],
            ['BSIT', 'BSIT'],
            ['Unconfigured Program', 'Unconfigured Program'],
            [null, '-'],
        ];
    }
}
