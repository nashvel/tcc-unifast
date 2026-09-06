<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContinuityRecordState extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['base' => 'encrypted:array', 'synced_at' => 'datetime'];
    }
}
