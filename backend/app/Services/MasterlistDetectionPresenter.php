<?php

namespace App\Services;

use App\Models\MasterlistImport;
use App\Models\MasterlistImportDetectedHeader;

/**
 * Serializes normalized table-detection metadata (DOCX/PDF imports) into the shape
 * the frontend expects.
 *
 * Split from MasterlistImportPresenter to keep both within their architecture line
 * budgets, and because detection is only relevant to a subset of upload formats.
 */
class MasterlistDetectionPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public function present(MasterlistImport $import): ?array
    {
        $detection = $import->detection;
        if (! $detection) {
            return null;
        }

        $headers = $detection->headers; // already ordered by position

        return [
            'table_index' => $detection->table_index,
            'raw_headers' => $headers->pluck('raw_header')->all(),
            'matched_columns' => $headers->whereNotNull('mapped_field')->mapWithKeys(
                fn (MasterlistImportDetectedHeader $header) => [$header->mapped_field => $header->raw_header]
            )->all(),
            'unmatched_headers' => $headers->whereNull('mapped_field')->pluck('raw_header')->values()->all(),
            'row_count' => $detection->detected_row_count,
        ];
    }
}
