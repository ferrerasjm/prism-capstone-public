<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_interventions', function (Blueprint $table): void {
            $table->dropColumn('summary');
        });
    }

    public function down(): void
    {
        Schema::table('risk_interventions', function (Blueprint $table): void {
            $table->string('summary', 200)->default('')->after('action_type');
        });
    }
};
