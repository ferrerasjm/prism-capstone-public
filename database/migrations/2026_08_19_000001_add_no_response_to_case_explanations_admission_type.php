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
            DB::statement("ALTER TABLE case_explanations MODIFY admission_type ENUM('full_admission', 'partial_admission', 'full_denial', 'no_response') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE case_explanations MODIFY admission_type ENUM('full_admission', 'partial_admission', 'full_denial') NOT NULL");
        }
    }
};
