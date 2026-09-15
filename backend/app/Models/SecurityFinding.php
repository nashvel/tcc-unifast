<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityFinding extends Model
{
    protected $fillable = ['title', 'description', 'category', 'severity', 'status', 'related_user_id', 'reported_by', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
