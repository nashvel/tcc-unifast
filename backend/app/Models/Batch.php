<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submission_deadline' => 'datetime',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function imports(): HasMany
    {
        return $this->hasMany(MasterlistImport::class);
    }

    public function grantees(): HasMany
    {
        return $this->hasMany(Grantee::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(BatchNotification::class);
    }

    public function billingReports(): HasMany
    {
        return $this->hasMany(BillingReport::class);
    }

    public function computedWindowStatus(): string
    {
        if (! $this->is_active) {
            return $this->closed_at ? 'closed' : 'draft';
        }

        if ($this->submission_deadline && $this->submission_deadline->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
