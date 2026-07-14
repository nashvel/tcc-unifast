<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'household_income' => 'decimal:2',
            'mismatches' => 'array',
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
}
