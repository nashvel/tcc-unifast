<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table): void {
            $table->timestamp('submission_deadline')->nullable()->after('semester');
            $table->boolean('is_active')->default(false)->after('submission_deadline');
            $table->string('window_status', 40)->default('draft')->after('is_active');
            $table->timestamp('activated_at')->nullable()->after('window_status');
            $table->timestamp('closed_at')->nullable()->after('activated_at');
        });

        Schema::create('batch_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_notifications');

        Schema::table('batches', function (Blueprint $table): void {
            $table->dropColumn([
                'submission_deadline',
                'is_active',
                'window_status',
                'activated_at',
                'closed_at',
            ]);
        });
    }
};
