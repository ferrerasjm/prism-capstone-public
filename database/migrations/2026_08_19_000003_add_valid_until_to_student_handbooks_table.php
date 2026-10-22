<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_handbooks', function (Blueprint $table): void {
            $table->date('valid_until')->nullable()->after('effective_date');
        });
    }

    public function down(): void
    {
        Schema::table('student_handbooks', function (Blueprint $table): void {
            $table->dropColumn('valid_until');
        });
    }
};
