<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseController extends Controller
{
    public function tables(): JsonResponse
    {
        $tables = Schema::getTables();
        $tableData = [];

        foreach ($tables as $tableInfo) {
            $table = $tableInfo['name'];
            $columns = Schema::getColumns($table);
            $count = DB::table($table)->count();

            $tableData[] = [
                'name' => $table,
                'columns' => count($columns),
                'rows' => $count,
                'column_names' => array_column($columns, 'name'),
            ];
        }

        usort($tableData, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json(['data' => $tableData]);
    }

    public function table(string $table): JsonResponse
    {
        if (!Schema::hasTable($table)) {
            return response()->json(['message' => "Table '{$table}' not found."], 404);
        }

        $columns = Schema::getColumns($table);
        $count = DB::table($table)->count();

        $columnDetails = array_map(function ($col) {
            return [
                'name' => $col['name'],
                'type' => $col['type'] ?? 'unknown',
                'nullable' => $col['nullable'] ?? false,
                'default' => $col['default'] ?? null,
                'primary' => $col['name'] === 'id',
            ];
        }, $columns);

        return response()->json([
            'data' => [
                'name' => $table,
                'columns' => $columnDetails,
                'indexes' => [],
                'row_count' => $count,
            ],
        ]);
    }

    public function rows(Request $request, string $table): JsonResponse
    {
        if (!Schema::hasTable($table)) {
            return response()->json(['message' => "Table '{$table}' not found."], 404);
        }

        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 25);
        $search = $request->input('search', '');
        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction', 'asc');

        $query = DB::table($table);

        if ($search) {
            $columns = Schema::getColumns($table);
            $query->where(function ($q) use ($search, $columns) {
                foreach ($columns as $col) {
                    $q->orWhere($col['name'], 'like', "%{$search}%");
                }
            });
        }

        $allowedSorts = array_column(Schema::getColumns($table), 'name');
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction === 'desc' ? 'desc' : 'asc');
        }

        $total = $query->count();
        $rows = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sql' => 'required|string|max:1000',
        ]);

        $sql = $validated['sql'];
        $lowerSql = strtolower(trim($sql));

        if (!str_starts_with($lowerSql, 'select')) {
            return response()->json(['message' => 'Only SELECT queries are allowed.'], 403);
        }

        $forbidden = ['drop', 'delete', 'truncate', 'alter', 'insert', 'update', 'create'];
        foreach ($forbidden as $word) {
            if (str_contains($lowerSql, $word)) {
                return response()->json(['message' => "Forbidden keyword '{$word}' in query."], 403);
            }
        }

        try {
            $start = microtime(true);
            $results = DB::select($sql);
            $elapsed = round((microtime(true) - $start) * 1000, 2);

            return response()->json([
                'data' => $results,
                'meta' => [
                    'count' => count($results),
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Query error: ' . $e->getMessage()], 422);
        }
    }

    public function stats(): JsonResponse
    {
        $tables = Schema::getTables();
        $stats = [];

        foreach ($tables as $tableInfo) {
            $table = $tableInfo['name'];
            $count = DB::table($table)->count();
            $columns = count(Schema::getColumns($table));

            $stats[] = [
                'table' => $table,
                'rows' => $count,
                'columns' => $columns,
            ];
        }

        usort($stats, fn ($a, $b) => $b['rows'] <=> $a['rows']);

        $totalRows = array_sum(array_column($stats, 'rows'));
        $totalTables = count($stats);

        return response()->json([
            'data' => [
                'tables' => $stats,
                'summary' => [
                    'total_tables' => $totalTables,
                    'total_rows' => $totalRows,
                    'database' => config('database.default'),
                ],
            ],
        ]);
    }
}
