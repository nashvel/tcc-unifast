<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OidcConnection extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['client_secret'];

    protected $attributes = [
        'singleton_key' => 'sis',
        'state' => 'draft',
        'revision' => 1,
        'student_id_claim' => 'student_id',
    ];

    protected function casts(): array
    {
        return ['client_secret' => 'encrypted', 'validated_at' => 'datetime', 'jwks_refreshed_at' => 'datetime', 'revision' => 'integer'];
    }

    public function available(): bool
    {
        return in_array($this->state, ['pilot', 'all_students'], true)
            && $this->validated_at !== null && $this->error_code === null;
    }
}
