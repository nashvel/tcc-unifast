<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('social_media_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->string('channel', 40)->default('facebook');
            $table->string('campaign', 120)->nullable();
            $table->string('status', 40)->default('draft');
            $table->string('approval_mode', 40)->default('approval_required');
            $table->text('message');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('n8n_request_id', 64)->nullable()->index();
            $table->string('n8n_status', 80)->nullable();
            $table->json('n8n_response')->nullable();
            $table->text('error_message')->nullable();
            $table->string('external_post_id')->nullable();
            $table->text('external_permalink')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index(['batch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_posts');
    }
};
