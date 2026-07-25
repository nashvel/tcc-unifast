<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequirementIdentityCheck extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'challenge_sequence' => 'array',
            'distance' => 'float',
            'confidence_score' => 'float',
            'manual_review_required' => 'boolean',
            'consent_accepted_at' => 'datetime',
            'checked_at' => 'datetime',
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

    public function documentSubmission(): BelongsTo
    {
        return $this->belongsTo(DocumentSubmission::class);
    }
}
