<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $fillable = ['title', 'content', 'version', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
