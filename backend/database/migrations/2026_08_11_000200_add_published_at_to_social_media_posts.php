<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('social_media_posts', function (Blueprint $table): void {
            $table->timestamp('published_at')->nullable()->after('submitted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('social_media_posts', function (Blueprint $table): void {
            $table->dropColumn('published_at');
        });
    }
};
