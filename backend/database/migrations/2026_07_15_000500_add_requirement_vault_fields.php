<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grantees', function (Blueprint $table): void {
            $table->string('submission_status', 40)->default('not_submitted')->after('status')->index();
            $table->timestamp('submitted_at')->nullable()->after('submission_status');
        });

        Schema::table('document_submissions', function (Blueprint $table): void {
            $table->foreignId('grantee_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->after('grantee_id')->constrained()->nullOnDelete();
            $table->string('slot_key', 60)->nullable()->after('batch_id')->index();
            $table->string('secondary_original_name')->nullable()->after('original_name');
            $table->string('secondary_stored_path')->nullable()->after('stored_path');
            $table->string('secondary_mime_type', 100)->nullable()->after('mime_type');
            $table->unsignedBigInteger('secondary_file_size')->nullable()->after('file_size');
            $table->text('face_descriptor_payload')->nullable()->after('metadata_payload');
            $table->decimal('face_quality_score', 4, 2)->nullable()->after('face_descriptor_payload');
            $table->boolean('identity_review_required')->default(false)->after('face_quality_score');
            $table->text('identity_review_reason')->nullable()->after('identity_review_required');

            $table->unique(['student_id', 'batch_id', 'slot_key'], 'document_submissions_student_batch_slot_unique');
        });

        Schema::create('requirement_identity_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grantee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_submission_id')->constrained()->cascadeOnDelete();
            $table->json('challenge_sequence');
            $table->string('result', 40)->index();
            $table->decimal('distance', 8, 6);
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->boolean('manual_review_required')->default(false);
            $table->timestamp('consent_accepted_at');
            $table->timestamp('checked_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_identity_checks');

        Schema::table('document_submissions', function (Blueprint $table): void {
            $table->dropUnique('document_submissions_student_batch_slot_unique');
            $table->dropConstrainedForeignId('grantee_id');
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn([
                'slot_key',
                'secondary_original_name',
                'secondary_stored_path',
                'secondary_mime_type',
                'secondary_file_size',
                'face_descriptor_payload',
                'face_quality_score',
                'identity_review_required',
                'identity_review_reason',
            ]);
        });

        Schema::table('grantees', function (Blueprint $table): void {
            $table->dropColumn(['submission_status', 'submitted_at']);
        });
    }
};
