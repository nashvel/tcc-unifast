<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'role', 'student_id', 'account_status', 'activated_at', 'password', 'email_verified_at', 'google_id', 'google_email_verified_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'google_email_verified_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function grantee(): HasOne
    {
        return $this->hasOne(Grantee::class);
    }

    public function kycProfile(): HasOne
    {
        return $this->hasOne(KycProfile::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles->whereIn('name', $roleNames)->isNotEmpty();
    }

    public function getAllPermissions(): Collection
    {
        if ($this->role === 'developer') {
            return Permission::all();
        }

        // If the user has direct module permissions assigned in permission_user, those take precedence
        $directPerms = $this->relationLoaded('permissions') ? $this->permissions : $this->permissions()->get();
        if ($directPerms->isNotEmpty()) {
            return $directPerms;
        }

        // Fallback to role baseline permissions
        return $this->roles->flatMap->permissions->unique('id');
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->role === 'developer') {
            return true;
        }

        return $this->getAllPermissions()->contains('name', $permissionName);
    }

    public function hasAnyPermission(array $permissionNames): bool
    {
        if ($this->role === 'developer') {
            return true;
        }

        return $this->getAllPermissions()->whereIn('name', $permissionNames)->isNotEmpty();
    }
}
