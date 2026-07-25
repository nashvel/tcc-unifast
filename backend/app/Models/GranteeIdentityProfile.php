<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GranteeIdentityProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_ocr_payload' => 'array',
            'onboarding_challenge_sequence' => 'array',
            'onboarding_face_distance' => 'float',
            'id_scan_completed_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantee(): BelongsTo
    {
        return $this->belongsTo(Grantee::class);
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed' && $this->onboarding_completed_at !== null;
    }
}
