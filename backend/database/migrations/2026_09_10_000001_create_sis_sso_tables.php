<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->change();
        });
        Schema::create('oidc_connections', function (Blueprint $table) {
            $table->id();
            $table->string('singleton_key')->default('sis')->unique();
            $table->string('display_name', 100);
            $table->string('issuer', 500);
            $table->string('client_id');
            $table->text('client_secret');
            $table->string('student_id_claim')->default('student_id');
            $table->string('state', 32)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('jwks_refreshed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->unsignedInteger('pilot_successes')->default(0);
            $table->unsignedInteger('pilot_failures')->default(0);
            $table->timestamps();
        });
        Schema::create('external_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('oidc_connections');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Hash of the exact, case-sensitive issuer + subject tuple avoids MySQL
            // collation and compound-index length changing OIDC identity semantics.
            $table->char('identity_key', 64)->unique();
            $table->text('issuer');
            $table->text('subject');
            $table->text('accepted_claims');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'user_id']);
        });
        Schema::create('sso_pilot_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('oidc_connections');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedInteger('successes')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->timestamps();
            $table->unique(['connection_id', 'user_id']);
        });
        Schema::create('sso_pilot_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('oidc_connections');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->char('token_hash', 64)->unique();
            $table->unsignedInteger('revision');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sso_login_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('oidc_connections');
            $table->foreignId('pilot_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->char('state_hash', 64)->unique();
            $table->char('browser_hash', 64);
            $table->unsignedInteger('revision');
            $table->text('context')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sso_identity_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('oidc_connections');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('identity_key', 64);
            $table->text('subject');
            $table->text('local_claims');
            $table->text('provider_claims');
            $table->string('reason', 64);
            $table->string('status', 20)->default('pending');
            $table->boolean('restricts_access')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('decision_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'restricts_access']);
        });
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->timestamp('absolute_expires_at')->nullable();
            $table->boolean('remembered')->default(false);
            $table->foreignId('sso_connection_id')->nullable()->constrained('oidc_connections');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sso_connection_id');
            $table->dropColumn(['absolute_expires_at', 'remembered']);
        });
        foreach (['sso_identity_reviews', 'sso_login_transactions', 'sso_pilot_invitations', 'sso_pilot_users', 'external_identities', 'oidc_connections'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
