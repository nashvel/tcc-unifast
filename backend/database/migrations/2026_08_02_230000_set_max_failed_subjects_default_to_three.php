<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('policy_settings')->updateOrInsert(
            ['key' => 'max_failed_subjects_per_semester'],
            [
                'value' => '3',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        Cache::forget('policy_setting:max_failed_subjects_per_semester');
    }

    public function down(): void
    {
        DB::table('policy_settings')
            ->where('key', 'max_failed_subjects_per_semester')
            ->update(['value' => '1', 'updated_at' => now()]);
        Cache::forget('policy_setting:max_failed_subjects_per_semester');
    }
};
