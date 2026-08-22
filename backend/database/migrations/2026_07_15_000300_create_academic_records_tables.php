<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grantee_id')->constrained()->cascadeOnDelete();
            $table->string('student_id', 100)->index();
            $table->string('student_number', 100)->nullable()->index();
            $table->string('grantee_name');
            $table->string('program');
            $table->string('year_level', 40)->nullable();
            $table->decimal('latest_gwa', 4, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('academic_semesters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_record_id')->constrained()->cascadeOnDelete();
            $table->string('term');
            $table->decimal('gwa', 4, 2)->nullable();
            $table->unsignedInteger('units_taken')->default(0);
            $table->unsignedInteger('units_passed')->default(0);
            $table->timestamps();
        });

        Schema::create('academic_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_semester_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('title');
            $table->unsignedInteger('units')->default(0);
            $table->string('grade', 40)->nullable();
            $table->string('remark', 40)->default('Passed');
            $table->timestamps();

            $table->index('remark');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_courses');
        Schema::dropIfExists('academic_semesters');
        Schema::dropIfExists('academic_records');
    }
};
