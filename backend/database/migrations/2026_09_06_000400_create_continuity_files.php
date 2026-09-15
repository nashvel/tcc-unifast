<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuity_files', function (Blueprint $table): void {
            $table->id();
            $table->string('path', 255)->unique();
            $table->string('drive_id');
            $table->string('file_id')->unique();
            $table->string('sha256', 64);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('status')->default('verified');
            $table->timestamps();
        });
        Schema::table('google_workspace_connections', function (Blueprint $table): void {
            $table->string('storage_folder_id')->nullable();
            $table->boolean('storage_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('google_workspace_connections', fn (Blueprint $table) => $table->dropColumn(['storage_folder_id', 'storage_enabled']));
        Schema::dropIfExists('continuity_files');
    }
};
