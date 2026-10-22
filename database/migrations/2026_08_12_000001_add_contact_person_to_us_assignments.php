<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('us_assignments', function (Blueprint $table): void {
            $table->string('contact_person_name', 150)->nullable()->after('supervisor_id');
            $table->string('contact_person_email')->nullable()->after('contact_person_name');
        });
    }

    public function down(): void
    {
        Schema::table('us_assignments', function (Blueprint $table): void {
            $table->dropColumn(['contact_person_name', 'contact_person_email']);
        });
    }
};
