<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormFieldOption extends Model
{
    protected $guarded = [];

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
