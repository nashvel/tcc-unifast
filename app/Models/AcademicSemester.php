<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSemester extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gwa' => 'decimal:2',
        ];
    }

    public function academicRecord(): BelongsTo
    {
        return $this->belongsTo(AcademicRecord::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(AcademicCourse::class);
    }
}
