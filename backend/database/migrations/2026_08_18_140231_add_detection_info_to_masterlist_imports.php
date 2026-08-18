<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 3NF-normalized detection metadata for DOCX/PDF masterlist imports.
 *
 * Instead of a JSON blob on masterlist_imports, we create two tables:
 *
 *   masterlist_import_detections
 *     One record per import. Stores which table was selected and the count.
 *
 *   masterlist_import_detected_headers
 *     One record per column found in the file.
 *     mapped_field is NULL when the column was not recognized.
 *
 * This satisfies 3NF:
 *   - 1NF: all values are atomic (no arrays or JSON blobs).
 *   - 2NF: every non-key attribute depends on the whole primary key.
 *   - 3NF: no transitive dependencies (mapped_field depends only on its PK,
 *           not on masterlist_import_id or raw_header independently).
 */
return new class extends Migration
{
    public function up(): void
    {
        // One detection result per import (1:1)
        Schema::create('masterlist_import_detections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('masterlist_import_id')
                ->unique()             // enforces 1:1 with masterlist_imports
                ->constrained('masterlist_imports')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('table_index')->default(0);   // which table in the doc (0-based)
            $table->unsignedInteger('detected_row_count')->default(0); // rows found before validation
            $table->timestamps();
        });

        // One row per column found in the selected table (1:N)
        Schema::create('masterlist_import_detected_headers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('detection_id')
                ->constrained('masterlist_import_detections')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position');  // column order (0-based)
            $table->string('raw_header', 200);         // exact text from the file
            $table->string('mapped_field', 60)->nullable(); // canonical field name, NULL if unrecognized
            $table->timestamps();

            // A given detection can only have one column per position
            $table->unique(['detection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masterlist_import_detected_headers');
        Schema::dropIfExists('masterlist_import_detections');
    }
};
