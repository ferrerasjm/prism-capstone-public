<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_explanation_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_explanation_id')->nullable();
            $table->foreign('case_explanation_id')
                ->references('id')
                ->on('case_explanations')
                ->nullOnDelete();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->enum('file_type', ['pdf', 'jpg', 'png']);
            $table->integer('file_size_bytes')->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_explanation_attachments');
    }
};
