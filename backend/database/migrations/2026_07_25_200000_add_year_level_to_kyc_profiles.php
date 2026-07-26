<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table): void {
            $table->string('year_level', 40)->nullable()->after('program');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table): void {
            $table->dropColumn('year_level');
        });
    }
};
