<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One detection result per MasterlistImport (1:1).
 * Records which table in the DOCX/PDF was selected and how many rows it had.
 */
class MasterlistImportDetection extends Model
{
    protected $guarded = [];

    public function masterlistImport(): BelongsTo
    {
        return $this->belongsTo(MasterlistImport::class);
    }

    /** @return HasMany<MasterlistImportDetectedHeader, $this> */
    public function headers(): HasMany
    {
        return $this->hasMany(MasterlistImportDetectedHeader::class, 'detection_id')
            ->orderBy('position');
    }

    /**
     * All headers that were successfully mapped to a canonical field.
     *
     * @return HasMany<MasterlistImportDetectedHeader, $this>
     */
    public function matchedHeaders(): HasMany
    {
        return $this->hasMany(MasterlistImportDetectedHeader::class, 'detection_id')
            ->whereNotNull('mapped_field')
            ->orderBy('position');
    }

    /**
     * Headers that did not match any known field alias.
     *
     * @return HasMany<MasterlistImportDetectedHeader, $this>
     */
    public function unmatchedHeaders(): HasMany
    {
        return $this->hasMany(MasterlistImportDetectedHeader::class, 'detection_id')
            ->whereNull('mapped_field')
            ->orderBy('position');
    }
}
