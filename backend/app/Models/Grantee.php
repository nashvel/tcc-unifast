<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Grantee extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function kycProfile(): HasOne
    {
        return $this->hasOne(KycProfile::class);
    }

    public function academicRecord(): HasOne
    {
        return $this->hasOne(AcademicRecord::class);
    }

    public function documentSubmissions(): HasMany
    {
        return $this->hasMany(DocumentSubmission::class);
    }

    public function identityChecks(): HasMany
    {
        return $this->hasMany(RequirementIdentityCheck::class);
    }

    public function identityProfile(): HasOne
    {
        return $this->hasOne(GranteeIdentityProfile::class);
    }

    public function pipelineResults(): HasMany
    {
        return $this->hasMany(SubmissionPipelineResult::class);
    }
}
