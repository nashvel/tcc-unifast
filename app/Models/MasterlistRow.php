<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterlistRow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'raw_payload' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(MasterlistImport::class, 'masterlist_import_id');
    }
}
