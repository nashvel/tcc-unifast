<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options'     => 'array',
            'is_required' => 'boolean',
            'is_locked'   => 'boolean',
            'sort_order'  => 'integer',
            'min_length'  => 'integer',
            'max_length'  => 'integer',
            'max_file_size' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** Fields that require an options list. */
    public static function choiceTypes(): array
    {
        return ['select', 'radio', 'checkbox'];
    }

    public function isChoiceType(): bool
    {
        return in_array($this->field_type, self::choiceTypes(), true);
    }
}
