<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('demo_audit_logs') && ! Schema::hasTable('audit_logs')) {
            Schema::rename('demo_audit_logs', 'audit_logs');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs') && ! Schema::hasTable('demo_audit_logs')) {
            Schema::rename('audit_logs', 'demo_audit_logs');
        }
    }
};
