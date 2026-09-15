<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SsoLoginTransaction extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['context', 'state_hash', 'browser_hash'];

    protected function casts(): array
    {
        return ['context' => 'encrypted:array', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
