<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the normalized options table
        Schema::create('form_field_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_field_id')
                ->constrained('form_fields')
                ->cascadeOnDelete();
            $table->string('option_value', 500);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Migrate existing JSON options → rows
        DB::table('form_fields')
            ->whereNotNull('options')
            ->orderBy('id')
            ->each(function (object $field): void {
                $options = json_decode($field->options, true);
                if (! is_array($options)) {
                    return;
                }
                foreach ($options as $index => $value) {
                    DB::table('form_field_options')->insert([
                        'form_field_id' => $field->id,
                        'option_value' => (string) $value,
                        'sort_order' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // 3. Keep the options JSON column for now as a backward-compat cache.
        //    It will be kept in sync by the model accessor.
    }

    public function down(): void
    {
        Schema::dropIfExists('form_field_options');
    }
};
