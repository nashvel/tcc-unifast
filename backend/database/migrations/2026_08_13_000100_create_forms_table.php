<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->string('public_token', 64)->unique()->nullable();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->enum('target_role', ['grantee', 'staff', 'all'])->default('grantee');
            $table->enum('visibility', ['public', 'private'])->default('private');
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('max_submissions')->nullable()->default(1);
            $table->timestamp('closes_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('label', 191);
            $table->string('field_name', 100);
            $table->enum('field_type', [
                'text', 'number', 'email', 'select',
                'radio', 'checkbox', 'textarea', 'date', 'file',
            ]);
            $table->string('placeholder', 191)->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('min_value', 50)->nullable();
            $table->string('max_value', 50)->nullable();
            $table->integer('min_length')->nullable();
            $table->integer('max_length')->nullable();
            $table->string('accepted_types', 191)->nullable();
            $table->integer('max_file_size')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['form_id', 'field_name'], 'unique_field_name_per_form');
        });

        Schema::create('form_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->restrictOnDelete();
            $table->foreignId('grantee_id')->nullable()->constrained('grantees')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->json('responses');
            $table->string('response_hash', 64)->nullable()->index();
            $table->boolean('is_authenticated')->default(true);
            $table->string('submitter_ip', 45)->nullable();
            $table->string('submitter_agent', 500)->nullable();
            $table->boolean('honeypot_triggered')->default(false);
            $table->timestamp('submitted_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['form_id', 'grantee_id', 'batch_id'],
                'one_response_per_grantee_per_form'
            );
        });

        Schema::create('form_security_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->nullable()->constrained('forms')->nullOnDelete();
            $table->enum('event_type', [
                'honeypot_triggered',
                'rate_limit_hit',
                'unauthorized_access',
                'token_enumeration_attempt',
                'invalid_field_submission',
                'duplicate_submission_attempt',
                'xss_attempt',
                'sql_injection_attempt',
            ]);
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_security_logs');
        Schema::dropIfExists('form_responses');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('forms');
    }
};
