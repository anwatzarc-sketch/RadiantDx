<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $role_id
 * @property bool $is_active
 * @property bool $must_change_password
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property-read Role|null $role
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Permission names resolved for this user, memoised for the request.
     *
     * @var array<int, string>|null
     */
    private ?array $resolvedPermissions = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Laravel calls this before establishing a session, so a deactivated account
     * cannot authenticate even with valid credentials.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissionNames(), true);
    }

    /** @param array<int, string> $permissions */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function permissionNames(): array
    {
        if ($this->resolvedPermissions !== null) {
            return $this->resolvedPermissions;
        }

        $role = $this->role;

        if ($role === null || ! $role->is_active) {
            return $this->resolvedPermissions = [];
        }

        if ($role->isSuperAdmin()) {
            return $this->resolvedPermissions = Permission::query()->pluck('name')->all();
        }

        return $this->resolvedPermissions = $role->permissions()->pluck('name')->all();
    }

    /** Drop the memoised permission list after the role or its grants change. */
    public function forgetResolvedPermissions(): void
    {
        $this->resolvedPermissions = null;
        $this->unsetRelation('role');
    }

    /**
     * True when this account is the only remaining way into the administration.
     *
     * Deleting or deactivating it, or moving it off its administrative role,
     * would leave the installation with no usable administrative access.
     */
    public function isLastActiveAdministrator(): bool
    {
        if (! $this->isSuperAdmin() || ! $this->is_active) {
            return false;
        }

        return self::query()
            ->where('is_active', true)
            ->whereKeyNot($this->getKey())
            ->whereHas('role', function (Builder $role): void {
                $role->where('is_super_admin', true)->where('is_active', true);
            })
            ->doesntExist();
    }

    public function roleName(): string
    {
        return $this->role?->name ?? 'No role assigned';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)), $parts);

        return implode('', array_slice(array_filter($letters), 0, 2)) ?: '?';
    }
}
