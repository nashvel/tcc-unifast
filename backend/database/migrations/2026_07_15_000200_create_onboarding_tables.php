<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('academic_year', 40);
            $table->string('semester', 80);
            $table->string('status', 40)->default('open');
            $table->timestamps();
        });

        Schema::create('masterlist_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('stored_path')->nullable();
            $table->string('status', 40)->default('previewed');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->timestamps();
        });

        Schema::create('masterlist_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('masterlist_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('student_id', 100)->nullable();
            $table->string('student_number', 100)->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('program')->nullable();
            $table->string('year_level', 40)->nullable();
            $table->string('status', 40)->default('valid');
            $table->json('errors')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'student_number']);
        });

        Schema::create('grantees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('student_id', 100)->unique();
            $table->string('student_number', 100)->nullable()->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('program');
            $table->string('year_level', 40)->nullable();
            $table->string('status', 40)->default('unverified');
            $table->timestamps();
        });

        Schema::create('activation_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kyc_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grantee_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('student_id', 100);
            $table->string('program');
            $table->date('birthdate')->nullable();
            $table->string('contact')->nullable();
            $table->text('address')->nullable();
            $table->string('guardian_name')->nullable();
            $table->decimal('household_income', 12, 2)->nullable();
            $table->string('status', 40)->default('pending');
            $table->json('mismatches')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_profiles');
        Schema::dropIfExists('activation_tokens');
        Schema::dropIfExists('grantees');
        Schema::dropIfExists('masterlist_rows');
        Schema::dropIfExists('masterlist_imports');
        Schema::dropIfExists('batches');
    }
};
