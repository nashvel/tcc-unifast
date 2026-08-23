<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->dropColumn(['full_name', 'student_id', 'program']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->string('full_name')->nullable();
            $table->string('student_id', 100)->nullable();
            $table->string('program')->nullable();
        });
    }
};
