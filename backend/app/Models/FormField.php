<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormField extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options'       => 'array', // kept for backward-compat; synced by controller
            'is_required'   => 'boolean',
            'is_locked'     => 'boolean',
            'sort_order'    => 'integer',
            'min_length'    => 'integer',
            'max_length'    => 'integer',
            'max_file_size' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'section_id');
    }

    public function fieldOptions(): HasMany
    {
        return $this->hasMany(FormFieldOption::class, 'form_field_id')->orderBy('sort_order');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(FormFieldCondition::class, 'form_field_id');
    }

    /** Returns options as a flat string array from the normalized table */
    public function getOptionsArrayAttribute(): array
    {
        return $this->fieldOptions->pluck('option_value')->toArray();
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
