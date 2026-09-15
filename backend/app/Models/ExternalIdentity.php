<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalIdentity extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['subject', 'accepted_claims', 'identity_key'];

    protected function casts(): array
    {
        return ['subject' => 'encrypted', 'accepted_claims' => 'encrypted:array', 'enabled' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public static function key(string $issuer, string $subject): string
    {
        return hash('sha256', json_encode([$issuer, $subject], JSON_THROW_ON_ERROR));
    }
}
