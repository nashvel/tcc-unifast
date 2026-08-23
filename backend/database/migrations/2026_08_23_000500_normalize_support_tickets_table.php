<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the normalized replies table (1NF fix)
        Schema::create('support_ticket_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        // 2. Modify support_tickets table (3NF fix for reporter/assignee names)
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn('replies'); // 1NF multi-valued violation
            $table->dropColumn('reporter'); // 3NF transitive violation
            $table->dropColumn('assignee'); // 3NF transitive violation

            $table->foreignId('reporter_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->after('reporter_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropForeign(['reporter_id']);
            $table->dropForeign(['assignee_id']);
            $table->dropColumn(['reporter_id', 'assignee_id']);

            $table->string('reporter')->after('status')->default('Unknown');
            $table->string('assignee')->after('reporter')->nullable();
            $table->json('replies')->after('description')->nullable();
        });

        Schema::dropIfExists('support_ticket_replies');
    }
};
