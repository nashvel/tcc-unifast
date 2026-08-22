<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_programs', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->decimal('pass_grade', 3, 1)->default(3.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('policy_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('academic_programs')->insert([
            [
                'code' => 'BSIT',
                'name' => 'Bachelor of Science in Information Technology',
                'pass_grade' => 3.0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('policy_settings')->insert([
            [
                'key' => 'max_failed_subjects_per_semester',
                'value' => '3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'auto_approve_risk_threshold',
                'value' => '20',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_pass_grade',
                'value' => '3.0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_settings');
        Schema::dropIfExists('academic_programs');
    }
};
