<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContinuityReview extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'note' => 'encrypted', 'reviewed_at' => 'datetime'];
    }
}
