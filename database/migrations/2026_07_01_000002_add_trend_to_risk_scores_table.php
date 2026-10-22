<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_scores', function (Blueprint $table): void {
            $table->enum('trend', ['increasing', 'stable', 'decreasing'])
                ->nullable()
                ->after('risk_band');
        });
    }

    public function down(): void
    {
        Schema::table('risk_scores', function (Blueprint $table): void {
            $table->dropColumn('trend');
        });
    }
};
