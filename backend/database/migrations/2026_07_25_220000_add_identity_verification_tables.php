<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grantee_identity_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grantee_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('status', 40)->default('pending_id_scan')->index();
            $table->string('id_reference_face_path')->nullable();
            $table->string('onboarding_selfie_path')->nullable();
            $table->text('id_qr_payload')->nullable();
            $table->json('id_ocr_payload')->nullable();
            $table->decimal('onboarding_face_distance', 8, 6)->nullable();
            $table->json('onboarding_challenge_sequence')->nullable();
            $table->timestamp('id_scan_completed_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->string('last_id_scan_ip', 45)->nullable();
            $table->string('last_liveness_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::table('requirement_identity_checks', function (Blueprint $table): void {
            $table->json('distances')->nullable()->after('distance');
            $table->string('selfie_path')->nullable()->after('distances');
            $table->boolean('liveness_confirmed')->default(false)->after('selfie_path');
        });

        Schema::create('submission_pipeline_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grantee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('risk_score')->default(0);
            $table->string('risk_badge', 20)->default('low')->index();
            $table->json('signals')->nullable();
            $table->json('eligibility')->nullable();
            $table->json('ocr_summary')->nullable();
            $table->string('n8n_status', 40)->nullable();
            $table->string('authenticity_status', 40)->nullable();
            $table->string('status', 40)->default('queued')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['grantee_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_pipeline_results');

        Schema::table('requirement_identity_checks', function (Blueprint $table): void {
            $table->dropColumn(['distances', 'selfie_path', 'liveness_confirmed']);
        });

        Schema::dropIfExists('grantee_identity_profiles');
    }
};
