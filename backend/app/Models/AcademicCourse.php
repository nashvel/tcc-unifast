<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicCourse extends Model
{
    protected $guarded = [];

    public function academicSemester(): BelongsTo
    {
        return $this->belongsTo(AcademicSemester::class);
    }
}
