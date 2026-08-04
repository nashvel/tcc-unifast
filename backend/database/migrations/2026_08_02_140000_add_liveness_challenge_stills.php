<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('grantee_identity_profiles', function (Blueprint $table): void {
            $table->string('liveness_challenge_1_path')->nullable()->after('onboarding_selfie_path');
            $table->string('liveness_challenge_2_path')->nullable()->after('liveness_challenge_1_path');
            $table->json('liveness_challenge_labels')->nullable()->after('liveness_challenge_2_path');
        });
    }

    public function down(): void
    {
        Schema::table('grantee_identity_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'liveness_challenge_1_path',
                'liveness_challenge_2_path',
                'liveness_challenge_labels',
            ]);
        });
    }
};
