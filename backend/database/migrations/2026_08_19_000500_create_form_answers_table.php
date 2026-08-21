<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create normalized answers table
        Schema::create('form_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_response_id')
                  ->constrained('form_responses')
                  ->cascadeOnDelete();
            $table->foreignId('form_field_id')
                  ->constrained('form_fields')
                  ->cascadeOnDelete();
            $table->longText('answer_value')->nullable();
            $table->timestamps();

            $table->unique(
                ['form_response_id', 'form_field_id'],
                'unique_answer_per_response_field'
            );
        });

        // 2. Migrate existing JSON responses → form_answers rows
        DB::table('form_responses')
            ->orderBy('id')
            ->each(function (object $response): void {
                $answers = json_decode($response->responses ?? '{}', true);
                if (! is_array($answers)) {
                    return;
                }

                // Build a lookup: field_name → form_field_id for this form
                $fieldMap = DB::table('form_fields')
                    ->where('form_id', $response->form_id)
                    ->pluck('id', 'field_name')
                    ->toArray();

                foreach ($answers as $fieldName => $value) {
                    $fieldId = $fieldMap[$fieldName] ?? null;
                    if ($fieldId === null) {
                        continue; // field was deleted, skip
                    }

                    $serialized = is_array($value)
                        ? json_encode($value)
                        : (string) $value;

                    DB::table('form_answers')->insertOrIgnore([
                        'form_response_id' => $response->id,
                        'form_field_id'    => $fieldId,
                        'answer_value'     => $serialized !== '' ? Crypt::encryptString($serialized) : $serialized,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            });

        // 3. Keep form_responses.responses JSON column for backward compat
        //    (Renderer.vue still uses the JSON directly for now)
    }

    public function down(): void
    {
        Schema::dropIfExists('form_answers');
    }
};
