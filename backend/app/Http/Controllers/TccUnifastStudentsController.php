<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TccUnifastStudentsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $validated = $request->validate([
            'after_student_id' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'updated_since' => ['nullable', 'date'],
        ]);

        $table = (string) config('services.tcc_unifast_n8n.student_table', 'students');
        abort_unless(preg_match('/^[A-Za-z0-9_]+$/', $table) === 1, 500, 'Invalid student table configuration.');
        abort_unless(Schema::hasTable($table), 503, "The configured student table [{$table}] does not exist.");
        abort_unless(Schema::hasColumn($table, 'student_id'), 503, 'The configured student table requires a student_id column.');

        $limit = (int) ($validated['limit'] ?? 500);
        $query = DB::table($table)->orderBy('student_id');

        if (! empty($validated['after_student_id'])) {
            $query->where('student_id', '>', $validated['after_student_id']);
        }

        if (! empty($validated['updated_since']) && Schema::hasColumn($table, 'updated_at')) {
            $query->where('updated_at', '>=', $validated['updated_since']);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $students = $rows->take($limit)->values();
        $lastStudent = $students->last();

        return response()->json([
            'data' => $students,
            'pagination' => [
                'limit' => $limit,
                'has_more' => $hasMore,
                'next_cursor' => $hasMore && $lastStudent ? (string) $lastStudent->student_id : null,
            ],
        ]);
    }

    private function authorizeRequest(Request $request): void
    {
        $configuredSecret = (string) config('services.tcc_unifast_n8n.endpoint_secret');
        $providedSecret = (string) $request->header('X-TCC-UniFAST-Endpoint-Key');

        abort_unless($configuredSecret !== '' && hash_equals($configuredSecret, $providedSecret), 401, 'Invalid synchronization endpoint key.');
    }
}
