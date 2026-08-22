<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements Module — 3NF Normalized Schema
 *
 * 3NF Analysis:
 * ─────────────────────────────────────────────────────────────────────────
 * VIOLATION IDENTIFIED (if channels were JSON in announcements):
 *   announcements.channels → multi-valued attribute → violates 1NF
 *   (non-atomic: one cell would store ["email","sms","in_app"])
 *
 * VIOLATION IDENTIFIED (if target batch/program IDs were in announcements):
 *   announcements.target_id → depends on audience_type (transitive) → violates 3NF
 *   (non-key column target_id depends on another non-key column audience_type)
 *
 * SOLUTION — DECOMPOSED INTO 3 TABLES:
 *
 * 1. `announcements`         — core announcement attributes (1 row per broadcast)
 * 2. `announcement_channels` — which delivery channels are used (1 row per channel per announcement)
 * 3. `announcement_targets`  — which specific batches/programs are targeted (1 row per target per announcement)
 *
 * Functional Dependencies (all in BCNF):
 *   announcements:          id → title, body, audience_type, status, created_by, scheduled_at, sent_at
 *   announcement_channels:  id → announcement_id, channel        (+ UNIQUE: announcement_id+channel)
 *   announcement_targets:   id → announcement_id, target_type, target_id  (+ UNIQUE: announcement_id+target_type+target_id)
 * ─────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Table 1: announcements
         *
         * Stores one row per announcement. All attributes are single-valued
         * and depend solely on the primary key (id). No transitive dependencies.
         *
         * audience_type controls SCOPE:
         *   'all'     → broadcast to all grantees regardless of batch/program
         *   'batch'   → targets listed in announcement_targets (target_type = batch)
         *   'program' → targets listed in announcement_targets (target_type = program)
         */
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();

            // Core content — atomic, single-valued
            $table->string('title');
            $table->text('body');

            // Audience scope — enum, single-valued (multi-valued channel & target
            // are separated into child tables to preserve 1NF)
            $table->enum('audience_type', ['all', 'batch', 'program'])->default('all');

            // Lifecycle state
            $table->enum('status', ['draft', 'scheduled', 'sent', 'cancelled'])->default('draft');

            // Ownership — FK to users, nullable so historical records survive user deletion
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Scheduling — nullable until assigned/sent
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            // Indexes for admin list queries (filter by status, audience)
            $table->index(['status', 'audience_type']);
            $table->index(['created_by', 'status']);
        });

        /**
         * Table 2: announcement_channels
         *
         * Resolves the multi-valued attribute violation:
         * One row per delivery channel per announcement.
         *
         * Without this table, storing channels as JSON ["email","sms"] in
         * announcements would violate 1NF (non-atomic cell).
         *
         * Functional Dependency: (announcement_id, channel) → channel
         * (The composite natural key is the determinant; no transitive deps.)
         */
        Schema::create('announcement_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->enum('channel', ['in_app', 'email', 'sms']);
            $table->timestamps();

            // Prevents duplicate channels per announcement (natural composite key)
            $table->unique(['announcement_id', 'channel']);
        });

        /**
         * Table 3: announcement_targets
         *
         * Resolves the transitive dependency violation:
         * If target_id were stored in announcements, it would depend on
         * audience_type (a non-key column) — a 3NF violation.
         *
         * One row per target per announcement.
         * target_type = 'batch'   → target_id references batches.id
         * target_type = 'program' → target_id references academic_programs.id
         *
         * (No FK constraint on target_id itself because the referenced table
         * varies by target_type; integrity is enforced at the application layer.)
         */
        Schema::create('announcement_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->enum('target_type', ['batch', 'program']);
            $table->unsignedBigInteger('target_id'); // references batches.id or academic_programs.id
            $table->timestamps();

            // Prevents duplicate targets per announcement
            $table->unique(['announcement_id', 'target_type', 'target_id'], 'announcement_targets_unique');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        // Drop child tables first to avoid FK constraint violations
        Schema::dropIfExists('announcement_targets');
        Schema::dropIfExists('announcement_channels');
        Schema::dropIfExists('announcements');
    }
};
