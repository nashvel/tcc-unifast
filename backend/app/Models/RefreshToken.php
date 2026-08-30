<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    /** Full-privilege session (abilities ['*']). */
    public const SCOPE_FULL = 'full';

    /** Identity-funnel session (abilities ['onboarding:identity']), pre-credential. */
    public const SCOPE_ONBOARDING = 'onboarding';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Rotation must never widen privileges: a token's scope is carried forward,
     * never re-derived from the request.
     */
    public function isFullScope(): bool
    {
        return ($this->scope ?? self::SCOPE_FULL) === self::SCOPE_FULL;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by');
    }
}
