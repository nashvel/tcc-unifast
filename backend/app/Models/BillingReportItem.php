<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingReportItem extends Model
{
    public const INCLUDED = 'included';

    public const EXCLUDED = 'excluded';

    /** @deprecated Use EXCLUDED */
    public const STATUS_EXCLUDED = self::EXCLUDED;

    /** @deprecated Use INCLUDED */
    public const STATUS_INCLUDED = self::INCLUDED;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stipend_amount' => 'decimal:2',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(BillingReport::class, 'billing_report_id');
    }

    public function grantee(): BelongsTo
    {
        return $this->belongsTo(Grantee::class);
    }
}
