<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ocr_payload' => 'array',
            'metadata_payload' => 'array',
            'face_descriptor_payload' => 'encrypted:array',
            'ocr_confidence' => 'float',
            'face_quality_score' => 'float',
            'identity_review_required' => 'boolean',
            'reviewed_at' => 'datetime',
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

    public function identityChecks(): HasMany
    {
        return $this->hasMany(RequirementIdentityCheck::class);
    }
}
