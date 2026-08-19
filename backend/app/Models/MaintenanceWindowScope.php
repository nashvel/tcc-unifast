<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWindowScope extends Model
{
    protected $guarded = [];

    public function maintenanceWindow(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWindow::class);
    }
}
