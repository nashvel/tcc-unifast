<?php

namespace App\Services;

class DatabaseViewerPolicy
{
    public const REDACTED_VALUE = '[redacted]';

    public function assertEnabled(): void
    {
        abort_unless((bool) config('services.database_viewer.enabled', false), 404);
    }

    public function assertAllowedTable(string $table): void
    {
        abort_unless($this->isAllowedTable($table), 404);
    }

    public function isAllowedTable(string $table): bool
    {
        return in_array($table, $this->allowedTables(), true);
    }

    /**
     * @return list<string>
     */
    public function allowedTables(): array
    {
        return array_values(array_filter((array) config('services.database_viewer.allowed_tables', [])));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function redactRow(array $row): array
    {
        foreach ($row as $column => $value) {
            if ($this->shouldRedactColumn($column)) {
                $row[$column] = self::REDACTED_VALUE;
            }
        }

        return $row;
    }

    public function shouldRedactColumn(string $column): bool
    {
        $normalized = strtolower($column);

        foreach ($this->redactedColumnFragments() as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function redactedColumnFragments(): array
    {
        return [
            'password',
            'remember_token',
            'access_token',
            'api_token',
            'token_hash',
            'secret',
            'face_descriptor',
            'ocr_payload',
            'metadata_payload',
            'stored_path',
        ];
    }
}
