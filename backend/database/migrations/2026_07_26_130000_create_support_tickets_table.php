<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id')->unique();
            $table->string('title');
            $table->string('category')->default('bug');
            $table->string('priority')->default('Normal');
            $table->string('status')->default('Open');
            $table->string('reporter');
            $table->string('assignee')->nullable();
            $table->text('description')->nullable();
            $table->json('replies')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
