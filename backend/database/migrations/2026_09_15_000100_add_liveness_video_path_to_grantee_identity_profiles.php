<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grantee_identity_profiles', function (Blueprint $table): void {
            // Stores the WebM/MP4 liveness motion recording for staff manual review.
            // NULL for the vast majority of students who auto-pass the biometric match
            // (ZONE_CONFIDENT). Only populated when the submission routes to
            // pending_face_review (ZONE_UNCERTAIN or ZONE_UNCERTAIN replay trap).
            // Purged from disk and set back to NULL when staff approves or rejects.
            $table->string('liveness_video_path', 500)->nullable()->after('liveness_challenge_labels');
        });
    }

    public function down(): void
    {
        Schema::table('grantee_identity_profiles', function (Blueprint $table): void {
            $table->dropColumn('liveness_video_path');
        });
    }
};
