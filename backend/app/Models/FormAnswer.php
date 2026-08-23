<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormAnswer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'answer_value' => 'encrypted',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'form_response_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    /**
     * Deserialize the stored answer value.
     * Arrays/objects are stored as JSON strings.
     */
    public function getDeserializedValueAttribute(): mixed
    {
        if ($this->answer_value === null) {
            return null;
        }
        $decoded = json_decode($this->answer_value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->answer_value;
    }
}
