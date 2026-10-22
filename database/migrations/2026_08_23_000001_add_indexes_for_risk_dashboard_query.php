<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table): void {
            $table->index('status');
        });

        Schema::table('risk_scores', function (Blueprint $table): void {
            $table->index('risk_band');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table): void {
            $table->dropIndex(['status']);
        });

        Schema::table('risk_scores', function (Blueprint $table): void {
            $table->dropIndex(['risk_band']);
        });
    }
};
