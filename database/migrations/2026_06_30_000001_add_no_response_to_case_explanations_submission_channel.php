<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE case_explanations MODIFY submission_channel ENUM('portal', 'physical_encoded_by_do', 'no_response') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE case_explanations MODIFY submission_channel ENUM('portal', 'physical_encoded_by_do') NOT NULL");
        }
    }
};
