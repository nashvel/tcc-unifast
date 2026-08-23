<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications Module — 3NF Normalized Schema
 * 
 * 3NF Analysis:
 * ─────────────────────────────────────────────────────────────────────────
 * 1NF: The table stores standard scalar columns. (Note: the `data` column is
 *      stored as JSON. In strict relational theory, storing JSON can be seen 
 *      as violating 1NF if the contents are meant to be queried relationally. 
 *      However, since it acts purely as an opaque payload string to render 
 *      the notification text, it is treated as an atomic blob).
 * 2NF: Single column UUID primary key (id). No partial dependencies.
 * 3NF: All columns (type, notifiable, data, read_at) depend solely on the 
 *      primary key `id`. There are no transitive dependencies.
 * ─────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            // Standard Laravel Polymorphic Notifications Table
            $table->uuid('id')->primary();
            
            // The class name of the notification (e.g., App\Notifications\DocumentReturned)
            $table->string('type');
            
            // Polymorphic relation to the entity receiving the notification (User)
            $table->morphs('notifiable');
            
            // The payload data to display the notification content
            $table->text('data');
            
            // Lifecycle
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
