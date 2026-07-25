<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionPipelineResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'signals' => 'array',
            'eligibility' => 'array',
            'ocr_summary' => 'array',
            'risk_score' => 'integer',
        ];
    }

    public function grantee(): BelongsTo
    {
        return $this->belongsTo(Grantee::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
