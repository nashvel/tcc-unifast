<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MasterlistImport extends Model
{
    protected $guarded = [];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(MasterlistRow::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Table-detection metadata (only present for DOCX/PDF imports). */
    public function detection(): HasOne
    {
        return $this->hasOne(MasterlistImportDetection::class);
    }
}
