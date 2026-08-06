<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Uploads previously wrote pending_review immediately. Demote those still owned by
 * grantees who have not final-submitted so staff queue only shows confirmed packages.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('document_submissions')
            || ! DB::getSchemaBuilder()->hasTable('grantees')) {
            return;
        }

        $ids = DB::table('document_submissions')
            ->join('grantees', 'grantees.id', '=', 'document_submissions.grantee_id')
            ->where('document_submissions.status', 'pending_review')
            ->where(function ($query): void {
                $query->whereNull('grantees.submission_status')
                    ->orWhereIn('grantees.submission_status', ['not_submitted', 'resubmission_requested']);
            })
            ->pluck('document_submissions.id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('document_submissions')
            ->whereIn('id', $ids->all())
            ->update(['status' => 'draft']);
    }

    public function down(): void
    {
        // Irreversible data repair — leave drafts as-is.
    }
};
