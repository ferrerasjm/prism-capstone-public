<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 'planned' is no longer a status a support action can hold, so the column default
     * that still named it was the last stale reference to the old two-step flow.
     */
    public function up(): void
    {
        Schema::table('risk_interventions', function (Blueprint $table): void {
            $table->string('status', 20)->default('in_progress')->change();
        });
    }

    public function down(): void
    {
        Schema::table('risk_interventions', function (Blueprint $table): void {
            $table->string('status', 20)->default('planned')->change();
        });
    }
};
