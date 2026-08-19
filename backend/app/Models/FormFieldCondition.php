<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormFieldCondition extends Model
{
    protected $guarded = [];

    /** The field that gets shown/hidden */
    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    /** The field whose value is checked */
    public function sourceField(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'source_field_id');
    }
}
