<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuity_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('record_state_id');
            $table->foreign('record_state_id')->references('id')->on('continuity_record_states')->restrictOnDelete();
            $table->unsignedBigInteger('revision');
            $table->text('snapshot');
            $table->timestamps();
            $table->unique(['record_state_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuity_revisions');
    }
};
