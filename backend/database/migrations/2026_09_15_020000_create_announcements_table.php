<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration
{
    public function up(): void
    {
        // The normalized announcements, announcement_channels, and
        // announcement_targets tables already exist from the canonical
        // 2026_08_23_000100 migration. Keep this historical migration a no-op
        // so environments that reached it before the correction can recover.
    }

    public function down(): void
    {
        // No-op: never drop the canonical announcements tables.
    }
};
