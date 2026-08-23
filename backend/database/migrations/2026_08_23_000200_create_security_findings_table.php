<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security Module — 3NF Normalized Schema
 * 
 * 3NF Analysis:
 * ─────────────────────────────────────────────────────────────────────────
 * 1NF: All attributes are single-valued and atomic.
 * 2NF: The primary key (id) is a single column, so no partial dependencies exist.
 * 3NF: All non-key attributes (title, severity, status, etc.) depend strictly 
 *      on the primary key (id). No transitive dependencies.
 * 
 * NOTE: If we needed to track a history of status changes per finding, storing
 * a JSON array of status changes in this table would violate 1NF. A separate
 * `security_finding_audits` table would be created for that. Here, we just store
 * the current state.
 * ─────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_findings', function (Blueprint $table): void {
            $table->id();
            
            // Core attributes
            $table->string('title');
            $table->text('description')->nullable();
            
            // Categorization
            $table->string('category')->default('general');
            
            // Severity and lifecycle status
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->enum('status', ['open', 'resolved', 'ignored'])->default('open');
            
            // Associations
            // If the finding pertains to a specific user (e.g. suspicious login)
            $table->foreignId('related_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Ownership / auditing
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            // Indexes for fast querying on the security dashboard
            $table->index(['status', 'severity']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_findings');
    }
};
