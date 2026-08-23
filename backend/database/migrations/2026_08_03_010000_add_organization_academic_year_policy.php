<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('policy_settings')->updateOrInsert(
            ['key' => 'organization_academic_year'],
            [
                'value' => '2026-2027',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        Cache::forget('policy_setting:organization_academic_year');
    }

    public function down(): void
    {
        DB::table('policy_settings')->where('key', 'organization_academic_year')->delete();
        Cache::forget('policy_setting:organization_academic_year');
    }
};
