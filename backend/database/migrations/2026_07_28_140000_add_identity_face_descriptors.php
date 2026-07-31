<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('grantee_identity_profiles', function (Blueprint $table): void {
            $table->text('id_reference_face_descriptor')->nullable()->after('id_reference_face_path');
            $table->text('onboarding_selfie_descriptor')->nullable()->after('onboarding_selfie_path');
        });
    }

    public function down(): void
    {
        Schema::table('grantee_identity_profiles', function (Blueprint $table): void {
            $table->dropColumn(['id_reference_face_descriptor', 'onboarding_selfie_descriptor']);
        });
    }
};
