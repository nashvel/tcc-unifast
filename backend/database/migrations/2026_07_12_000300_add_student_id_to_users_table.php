<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('student_id', 100)->nullable()->unique()->after('role'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['student_id'])->dropColumn('student_id'));
    }
};
