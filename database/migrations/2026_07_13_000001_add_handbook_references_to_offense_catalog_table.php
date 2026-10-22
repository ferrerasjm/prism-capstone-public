<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offense_catalog', function (Blueprint $table): void {
            $table->string('handbook_section', 50)->nullable()->after('description');
            $table->string('handbook_page', 10)->nullable()->after('handbook_section');
        });

        $this->backfillHandbookReferences();
    }

    public function down(): void
    {
        Schema::table('offense_catalog', function (Blueprint $table): void {
            $table->dropColumn(['handbook_section', 'handbook_page']);
        });
    }

    private function backfillHandbookReferences(): void
    {
        foreach (range(1, 15) as $number) {
            $code = 'MINOR-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);

            DB::table('offense_catalog')
                ->where('code', $code)
                ->update($this->handbookReferenceFor($code, 'minor'));
        }

        foreach (range(1, 47) as $number) {
            $code = 'MAJOR-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);

            DB::table('offense_catalog')
                ->where('code', $code)
                ->update($this->handbookReferenceFor($code, 'major'));
        }
    }

    private function handbookReferenceFor(string $code, string $offenseType): array
    {
        if ($offenseType === 'minor') {
            return [
                'handbook_section' => 'Section VI.D',
                'handbook_page' => match ($code) {
                    'MINOR-01', 'MINOR-02' => '33',
                    'MINOR-03' => '33-34',
                    default => '34',
                },
            ];
        }

        $offenseNumber = (int) substr($code, 6);

        return [
            'handbook_section' => 'Section VI.E',
            'handbook_page' => match (true) {
                $offenseNumber <= 3 => '35',
                $offenseNumber <= 16 => '36',
                $offenseNumber <= 30 => '37',
                $offenseNumber <= 45 => '38',
                default => '39',
            },
        ];
    }
};
