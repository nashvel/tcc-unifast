<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceWindow extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'affects_all' => 'boolean',
            'blocks_access' => 'boolean',
            'allow_staff_bypass' => 'boolean',
            'show_on_landing' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(MaintenanceWindowScope::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isCurrentlyActive(): bool
    {
        $now = now();

        if (! in_array($this->status, ['scheduled', 'active'], true)) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        return ! $this->ends_at || $this->ends_at->isFuture();
    }
}
