<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_offenses', function (Blueprint $table): void {
            $table->foreignId('removed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable()->after('removed_by');
            $table->text('removal_reason')->nullable()->after('removed_at');
        });
    }

    public function down(): void
    {
        Schema::table('case_offenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('removed_by');
            $table->dropColumn(['removed_at', 'removal_reason']);
        });
    }
};
