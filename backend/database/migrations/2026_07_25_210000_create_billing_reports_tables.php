<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->unsignedInteger('total_grantees')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('stipend_per_grantee', 12, 2)->default(10000);
            $table->string('file_path')->nullable();
            $table->boolean('is_supplemental')->default(false);
            $table->foreignId('parent_report_id')->nullable()->constrained('billing_reports')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'batch_id']);
        });

        Schema::create('billing_report_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grantee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('student_id', 100);
            $table->string('program')->nullable();
            $table->decimal('stipend_amount', 12, 2)->default(0);
            $table->string('inclusion_status', 40);
            $table->string('exclusion_reason')->nullable();
            $table->timestamps();

            $table->index(['billing_report_id', 'inclusion_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_report_items');
        Schema::dropIfExists('billing_reports');
    }
};
