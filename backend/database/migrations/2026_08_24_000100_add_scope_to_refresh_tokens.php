<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity-first activation, Phase 1.
 *
 * Tags each refresh token with the scope of the session that issued it so a
 * rotation can never widen its own privileges. Existing rows default to 'full',
 * which preserves current behaviour for every signed-in user.
 *
 * See docs/identity-first-activation-implementation-plan.md (invariant I3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table): void {
            $table->string('scope', 20)->default('full')->after('family_id');

            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table): void {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
        });
    }
};
