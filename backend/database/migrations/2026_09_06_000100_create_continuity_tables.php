<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_workspace_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('singleton')->default('organization')->unique();
            $table->string('google_subject')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('disconnected');
            $table->string('drive_id')->nullable();
            $table->string('drive_name')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('continuity_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('module')->unique();
            $table->string('workbook_id')->nullable()->unique();
            $table->string('folder_id')->nullable();
            $table->string('form_id')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestamps();
        });
        Schema::create('continuity_sync_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('request_hash', 64);
            $table->string('source');
            $table->string('status')->default('queued')->index();
            $table->json('summary')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('continuity_record_states', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('module');
            $table->unsignedBigInteger('record_id');
            $table->text('base')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['module', 'record_id']);
        });
        Schema::create('continuity_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('record_state_id')->nullable();
            $table->foreign('record_state_id')->references('id')->on('continuity_record_states')->restrictOnDelete();
            $table->string('module');
            $table->string('kind');
            $table->string('fingerprint', 64)->unique();
            $table->text('payload');
            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['continuity_reviews', 'continuity_record_states', 'continuity_sync_runs', 'continuity_resources', 'google_workspace_connections'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
