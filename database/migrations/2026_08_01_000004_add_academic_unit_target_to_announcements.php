<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->foreignId('target_academic_unit_id')
                ->nullable()
                ->after('target_role')
                ->constrained('academic_units')
                ->cascadeOnDelete();

            $table->index(
                ['target_role', 'target_academic_unit_id', 'created_at'],
                'announcements_role_unit_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex('announcements_role_unit_created_index');
            $table->dropConstrainedForeignId('target_academic_unit_id');
        });
    }
};
