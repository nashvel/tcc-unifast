<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->string('document_type', 20)->default('terms');
            $table->index(['document_type', 'is_active'], 'terms_document_type_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->dropIndex('terms_document_type_active_index');
            $table->dropColumn('document_type');
        });
    }
};
