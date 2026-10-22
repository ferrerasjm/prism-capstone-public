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
        Schema::table('risk_score_snapshots', function (Blueprint $table): void {
            $table->string('term', 20)->nullable()->change();
            $table->string('snapshot_type', 20)->default('event')->after('id');
            $table->string('trigger', 50)->default('unspecified')->after('term');
            $table->decimal('previous_composite_score', 5, 2)->nullable()->after('composite_score');
            $table->string('previous_risk_band', 20)->nullable()->after('risk_band');
            $table->json('contributing_factors')->nullable()->after('previous_risk_band');
            $table->json('factor_changes')->nullable()->after('contributing_factors');
            $table->string('methodology_version', 20)->nullable()->after('factor_changes');
            $table->foreignId('triggered_by')->nullable()->after('methodology_version')
                ->constrained('users')->nullOnDelete();
            $table->index(['student_id', 'created_at'], 'risk_snapshots_student_created_index');
        });
    }

    public function down(): void
    {
        DB::table('risk_score_snapshots')
            ->whereNull('term')
            ->update(['term' => 'event']);

        Schema::table('risk_score_snapshots', function (Blueprint $table): void {
            $table->dropIndex('risk_snapshots_student_created_index');
            $table->dropConstrainedForeignId('triggered_by');
            $table->dropColumn([
                'snapshot_type',
                'trigger',
                'previous_composite_score',
                'previous_risk_band',
                'contributing_factors',
                'factor_changes',
                'methodology_version',
            ]);
            $table->string('term', 20)->nullable(false)->change();
        });
    }
};
