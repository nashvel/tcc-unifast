<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleWorkspaceConnection extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'refresh_token', 'google_subject'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime', 'validated_at' => 'datetime', 'enabled' => 'boolean'];
    }
}
