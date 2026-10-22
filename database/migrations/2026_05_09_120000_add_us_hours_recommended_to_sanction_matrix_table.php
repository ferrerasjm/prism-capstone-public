<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sanction_matrix', 'us_hours_recommended')) {
            Schema::table('sanction_matrix', function (Blueprint $table): void {
                $table->decimal('us_hours_recommended', 6, 2)->nullable()->after('sanction_category');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sanction_matrix', 'us_hours_recommended')) {
            Schema::table('sanction_matrix', function (Blueprint $table): void {
                $table->dropColumn('us_hours_recommended');
            });
        }
    }
};
