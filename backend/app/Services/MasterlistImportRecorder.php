<?php

namespace App\Services;

use App\Models\MasterlistImport;
use App\Models\MasterlistImportDetectedHeader;
use App\Models\MasterlistImportDetection;
use App\Models\MasterlistRow;

/**
 * Persists a parsed masterlist upload: detection metadata plus validated rows.
 *
 * Extracted from MasterlistImportController::preview(), which had grown past its
 * architecture line budget by carrying persistence, normalisation, and validation
 * bookkeeping inline.
 */
class MasterlistImportRecorder
{
    public function __construct(
        private readonly MasterlistImportRowValidator $rowValidator,
    ) {}

    /**
     * Store table-detection metadata in the normalized tables (DOCX/PDF only).
     *
     * @param  array<string, mixed>|null  $detectionInfo
     */
    public function recordDetection(MasterlistImport $import, ?array $detectionInfo): void
    {
        if (! is_array($detectionInfo)) {
            return;
        }

        $detection = MasterlistImportDetection::create([
            'masterlist_import_id' => $import->id,
            'table_index' => $detectionInfo['table_index'] ?? 0,
            'detected_row_count' => $detectionInfo['row_count'] ?? 0,
        ]);

        $rawHeaders = (array) ($detectionInfo['raw_headers'] ?? []);
        // matched_columns is { field => raw_header }; invert for header lookup.
        $rawToField = array_flip((array) ($detectionInfo['matched_columns'] ?? []));

        $headerRows = [];
        foreach ($rawHeaders as $position => $rawHeader) {
            $headerRows[] = [
                'detection_id' => $detection->id,
                'position' => $position,
                'raw_header' => (string) $rawHeader,
                'mapped_field' => $rawToField[$rawHeader] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($headerRows !== []) {
            MasterlistImportDetectedHeader::insert($headerRows);
        }
    }

    /**
     * Normalize, validate, and store each parsed row, then update import totals.
     *
     * Duplicate detection is cumulative across the file: each accepted row feeds the
     * seen-ID lists so a later duplicate is flagged against earlier rows.
     *
     * @param  list<array<string, mixed>>  $parsedRows
     */
    public function recordRows(MasterlistImport $import, array $parsedRows): void
    {
        $seenStudentIds = [];
        $seenStudentNumbers = [];
        $validRows = 0;
        $invalidRows = 0;

        $validPrograms = $this->rowValidator->activePrograms();

        foreach ($parsedRows as $index => $row) {
            $normalized = $this->rowValidator->normalize($row);
            $errors = $this->rowValidator->errors($normalized, $seenStudentIds, $seenStudentNumbers, $validPrograms);
            $status = $errors === [] ? 'valid' : 'invalid';

            $validRows += $status === 'valid' ? 1 : 0;
            $invalidRows += $status === 'invalid' ? 1 : 0;

            if (($normalized['student_id'] ?? '') !== '') {
                $seenStudentIds[] = $normalized['student_id'];
            }
            if (($normalized['student_number'] ?? '') !== '') {
                $seenStudentNumbers[] = $normalized['student_number'];
            }

            MasterlistRow::create([
                'masterlist_import_id' => $import->id,
                // +2: header row plus 1-based spreadsheet numbering.
                'row_number' => $index + 2,
                ...$normalized,
                'status' => $status,
                'errors' => $errors,
                'raw_payload' => $row,
            ]);
        }

        $import->update([
            'total_rows' => count($parsedRows),
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
        ]);
    }
}
