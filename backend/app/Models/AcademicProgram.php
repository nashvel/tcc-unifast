<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicProgram extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pass_grade' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
