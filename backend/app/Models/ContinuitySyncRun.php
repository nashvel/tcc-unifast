<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContinuitySyncRun extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
