<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->enum('notice_type', [
                'notice_to_explain',
                'notice_of_hearing',
                'notice_of_formative_intervention',
            ]);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->string('handbook_section', 50);
            $table->string('handbook_page', 10);
            $table->date('incident_date');
            $table->date('response_deadline');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('scheduled_location', 255)->nullable();
            $table->json('additional_content')->nullable();
            $table->timestamp('student_acknowledged_at')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_notices');
    }
};
