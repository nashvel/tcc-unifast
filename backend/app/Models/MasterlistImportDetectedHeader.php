<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per column found in the selected table of the DOCX/PDF.
 * mapped_field is NULL when the column was not recognized by the alias map.
 */
class MasterlistImportDetectedHeader extends Model
{
    protected $guarded = [];

    public function detection(): BelongsTo
    {
        return $this->belongsTo(MasterlistImportDetection::class, 'detection_id');
    }

    /** True when this column was mapped to a known system field. */
    public function isMatched(): bool
    {
        return $this->mapped_field !== null;
    }
}
