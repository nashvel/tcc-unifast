<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingReport extends Model
{
    public const TYPE_CALL_FOR_BILLING = 'call_for_billing';

    public const TYPE_DISTRIBUTION = 'distribution';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'stipend_per_grantee' => 'decimal:2',
            'is_supplemental' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function parentReport(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_report_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingReportItem::class);
    }
}
