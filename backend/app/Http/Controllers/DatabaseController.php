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
        $seen = [];

        foreach ($tables as $tableInfo) {
            $table = is_array($tableInfo) ? ($tableInfo['name'] ?? null) : (is_object($tableInfo) ? ($tableInfo->name ?? null) : (string) $tableInfo);
            if (!$table || in_array($table, $seen, true)) {
                continue;
            }
            $seen[] = $table;

            try {
                $columns = Schema::getColumns($table);
                $count = DB::table($table)->count();
            } catch (\Throwable $e) {
                // Ignore missing views or permission errors on system tables
                continue;
            }

            $tableData[] = [
                'name' => $table,
                'columns' => count($columns),
                'rows' => $count,
                'column_names' => array_column($columns, 'name'),
            ];
        }

        usort($tableData, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $totalRows = array_sum(array_column($tableData, 'rows'));
        $totalTables = count($tableData);
        $largest = null;
        foreach ($tableData as $t) {
            if (!$largest || $t['rows'] > $largest['rows']) {
                $largest = $t;
            }
        }

        return response()->json([
            'data' => $tableData,
            'summary' => [
                'total_tables' => $totalTables,
                'total_rows' => $totalRows,
                'database' => strtoupper(DB::connection()->getDriverName()),
                'largest_table' => $largest ? "{$largest['name']} ({$largest['rows']} rows)" : 'None',
            ],
        ]);
    }

    public function table(string $table): JsonResponse
    {
        if (!Schema::hasTable($table)) {
            return response()->json(['message' => "Table '{$table}' not found."], 404);
        }

        try {
            $columns = Schema::getColumns($table);
            $count = DB::table($table)->count();
        } catch (\Throwable $e) {
            return response()->json(['message' => "Unable to inspect table '{$table}'."], 500);
        }

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

        try {
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
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

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

    public function stats(): JsonResponse
    {
        $tables = Schema::getTables();
        $stats = [];
        $seen = [];

        foreach ($tables as $tableInfo) {
            $table = is_array($tableInfo) ? ($tableInfo['name'] ?? null) : (is_object($tableInfo) ? ($tableInfo->name ?? null) : (string) $tableInfo);
            if (!$table || in_array($table, $seen, true)) {
                continue;
            }
            $seen[] = $table;

            try {
                $count = DB::table($table)->count();
                $columns = count(Schema::getColumns($table));
            } catch (\Throwable $e) {
                continue;
            }

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
