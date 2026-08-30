<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity-first activation, Phase 1.
 *
 * Under Option A the activation link is no longer consumed on first click — it
 * stays usable for the whole identity funnel and is only spent when credentials
 * are finally created. That needs two extra pieces of state:
 *
 * - first_used_at         : when /begin was first hit (distinct from used_at,
 *                           which still marks single-use consumption).
 * - onboarding_session_id : the live scoped PAT for this token, so issuing a new
 *                           onboarding session revokes the previous one.
 *
 * See docs/identity-first-activation-implementation-plan.md §2.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activation_tokens', function (Blueprint $table): void {
            $table->timestamp('first_used_at')->nullable()->after('expires_at');

            // personal_access_tokens is created by Sanctum; nullOnDelete keeps the
            // row valid when the scoped token is revoked or expires away.
            $table->foreignId('onboarding_session_id')
                ->nullable()
                ->after('used_at')
                ->constrained('personal_access_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activation_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('onboarding_session_id');
            $table->dropColumn('first_used_at');
        });
    }
};
