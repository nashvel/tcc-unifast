<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table): void {
            $table->foreignId('section_id')
                  ->nullable()
                  ->after('form_id')
                  ->constrained('form_sections')
                  ->nullOnDelete();
        });

        // For each form that already has fields, create a default "General" section
        // and assign all existing un-sectioned fields to it.
        $formIds = DB::table('form_fields')
            ->whereNull('section_id')
            ->distinct()
            ->pluck('form_id');

        foreach ($formIds as $formId) {
            $sectionId = DB::table('form_sections')->insertGetId([
                'form_id'    => $formId,
                'title'      => 'General',
                'description' => null,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('form_fields')
                ->where('form_id', $formId)
                ->whereNull('section_id')
                ->update(['section_id' => $sectionId]);
        }
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table): void {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
        });
    }
};
