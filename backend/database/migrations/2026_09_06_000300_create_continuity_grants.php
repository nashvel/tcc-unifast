<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuity_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('resource_id')->constrained('continuity_resources')->restrictOnDelete();
            $table->string('email');
            $table->string('access');
            $table->string('google_permission_id')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuity_grants');
    }
};
