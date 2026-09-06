<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContinuityRevision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'encrypted:array'];
    }
}
