<?php

namespace App\Services;

use App\Models\MasterlistImport;
use App\Models\MasterlistRow;

class MasterlistImportPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function listRow(MasterlistImport $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status,
            'original_name' => $import->original_name,
            'total_rows' => $import->total_rows,
            'valid_rows' => $import->valid_rows,
            'invalid_rows' => $import->invalid_rows,
            'imported_rows' => $import->imported_rows,
            'created_at' => $import->created_at,
            'batch' => $this->batch($import),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function import(MasterlistImport $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status,
            'original_name' => $import->original_name,
            'total_rows' => $import->total_rows,
            'valid_rows' => $import->valid_rows,
            'invalid_rows' => $import->invalid_rows,
            'imported_rows' => $import->imported_rows,
            'batch' => $this->batch($import, includeDeadline: true),
            'rows' => $import->rows->map(fn (MasterlistRow $row) => [
                'id' => $row->id,
                'row_number' => $row->row_number,
                'student_id' => $row->student_id,
                'student_number' => $row->student_number,
                'full_name' => $row->full_name,
                'email' => $row->email,
                'program' => $row->program,
                'year_level' => $row->year_level,
                'status' => $row->status,
                'errors' => $row->errors ?? [],
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function batch(MasterlistImport $import, bool $includeDeadline = false): ?array
    {
        if (! $import->batch) {
            return null;
        }

        $batch = [
            'id' => $import->batch->id,
            'name' => $import->batch->name,
            'academic_year' => $import->batch->academic_year,
            'semester' => $import->batch->semester,
        ];

        if ($includeDeadline) {
            $batch['submission_deadline'] = $import->batch->submission_deadline;
        }

        return $batch;
    }
}
