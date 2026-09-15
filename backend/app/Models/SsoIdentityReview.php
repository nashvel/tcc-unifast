<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SsoIdentityReview extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['subject', 'identity_key'];

    protected function casts(): array
    {
        return ['subject' => 'encrypted', 'local_claims' => 'encrypted:array', 'provider_claims' => 'encrypted:array', 'decision_notes' => 'encrypted', 'restricts_access' => 'boolean', 'reviewed_at' => 'datetime'];
    }
}
