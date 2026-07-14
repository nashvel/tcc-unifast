<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latest_gwa' => 'decimal:2',
        ];
    }

    public function grantee(): BelongsTo
    {
        return $this->belongsTo(Grantee::class);
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(AcademicSemester::class);
    }
}
