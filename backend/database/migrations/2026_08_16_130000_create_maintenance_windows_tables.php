<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance_windows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('status', 40)->default('draft');
            $table->string('severity', 40)->default('maintenance');
            $table->boolean('affects_all')->default(false);
            $table->boolean('blocks_access')->default(true);
            $table->boolean('allow_staff_bypass')->default(false);
            $table->boolean('show_on_landing')->default(false);
            $table->string('landing_title', 160)->nullable();
            $table->text('landing_message')->nullable();
            $table->string('landing_cta_label', 80)->nullable();
            $table->text('landing_cta_url')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['affects_all', 'blocks_access']);
            $table->index(['show_on_landing', 'status']);
        });

        Schema::create('maintenance_window_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_window_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type', 40);
            $table->string('scope_key', 120);
            $table->string('label', 160)->nullable();
            $table->timestamps();

            $table->unique(['maintenance_window_id', 'scope_type', 'scope_key'], 'maintenance_scope_unique');
            $table->index(['scope_type', 'scope_key'], 'maintenance_scope_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_window_scopes');
        Schema::dropIfExists('maintenance_windows');
    }
};
