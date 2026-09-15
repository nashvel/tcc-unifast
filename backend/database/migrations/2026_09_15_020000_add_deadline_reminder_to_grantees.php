<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grantees', function (Blueprint $table): void {
            if (! Schema::hasColumn('grantees', 'last_deadline_reminder_sent_at')) {
                $table->timestamp('last_deadline_reminder_sent_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grantees', function (Blueprint $table): void {
            if (Schema::hasColumn('grantees', 'last_deadline_reminder_sent_at')) {
                $table->dropColumn('last_deadline_reminder_sent_at');
            }
        });
    }
};
