<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'closes_at' => 'datetime',
            'max_submissions' => 'integer',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class)->orderBy('sort_order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function securityLogs(): HasMany
    {
        return $this->hasMany(FormSecurityLog::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Whether the form is currently accepting new submissions. */
    public function isAcceptingSubmissions(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->closes_at && $this->closes_at->isPast()) {
            return false;
        }

        return true;
    }

    /** Whether every select/radio/checkbox field has at least 2 options. */
    public function allChoiceFieldsHaveOptions(): bool
    {
        $choiceTypes = ['select', 'radio', 'checkbox'];

        return $this->fields()
            ->whereIn('field_type', $choiceTypes)
            ->with('fieldOptions')
            ->get()
            ->every(fn (FormField $f) => $f->fieldOptions->count() >= 2);
    }

    /** Publish the form. */
    public function publish(): void
    {
        $this->update(['status' => 'published', 'is_active' => true]);
    }

    /** Close the form to new submissions. */
    public function close(): void
    {
        $this->update(['status' => 'closed', 'is_active' => false]);
    }

    /** Archive the form. */
    public function archive(): void
    {
        $this->update(['status' => 'archived', 'is_active' => false]);
    }
}
