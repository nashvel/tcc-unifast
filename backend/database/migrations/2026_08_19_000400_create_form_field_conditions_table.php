<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_field_conditions', function (Blueprint $table): void {
            $table->id();
            // The field that is SHOWN/HIDDEN based on the condition
            $table->foreignId('form_field_id')
                  ->constrained('form_fields')
                  ->cascadeOnDelete();
            // The source field whose value is checked
            $table->foreignId('source_field_id')
                  ->constrained('form_fields')
                  ->cascadeOnDelete();
            $table->enum('operator', [
                'equals',
                'not_equals',
                'contains',
                'greater_than',
                'less_than',
                'is_answered',
                'is_not_answered',
            ]);
            $table->string('condition_value', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_field_conditions');
    }
};
