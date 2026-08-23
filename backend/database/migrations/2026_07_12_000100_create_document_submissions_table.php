<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('student_id', 100)->index();
            $table->string('student_name');
            $table->string('document_type');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('status', 40)->default('pending_review')->index();
            $table->string('risk_level', 20)->default('low');
            $table->longText('extracted_text')->nullable();
            $table->decimal('ocr_confidence', 5, 2)->nullable();
            $table->json('ocr_payload')->nullable();
            $table->json('metadata_payload')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor');
            $table->string('role', 50);
            $table->string('action');
            $table->string('module', 100);
            $table->string('target')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('document_submissions');
    }
};
