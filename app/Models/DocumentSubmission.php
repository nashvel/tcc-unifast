<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ocr_payload' => 'array',
            'metadata_payload' => 'array',
            'ocr_confidence' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }
}
