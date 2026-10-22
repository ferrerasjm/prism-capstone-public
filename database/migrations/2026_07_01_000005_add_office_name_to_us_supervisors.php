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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('office_name', 255)->nullable()->after('role');
        });

        DB::table('users')
            ->where('role', 'us_supervisor')
            ->whereNull('office_name')
            ->update(['office_name' => 'Student Discipline Office']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('office_name');
        });
    }
};
