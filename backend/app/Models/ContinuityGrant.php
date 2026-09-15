<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContinuityGrant extends Model
{
    protected $guarded = ['id'];

    public function isEligible(): bool
    {
        $user = $this->user;
        if (! $user || $user->account_status !== 'active' || ! $user->google_id || ! $user->google_email_verified_at
            || strcasecmp($user->email, $this->email) !== 0 || ! in_array($this->access, ['read', 'write'], true)) {
            return false;
        }
        $roles = $user->roles;

        return $roles->isNotEmpty()
            ? $roles->whereIn('name', ['admin', 'developer', 'head', 'staff'])->isNotEmpty()
            : in_array($user->role, ['admin', 'developer', 'head', 'staff'], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ContinuityResource::class, 'resource_id');
    }
}
