<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_super_admin
 * @property-read Collection<int, Permission> $permissions
 * @property-read Collection<int, User> $users
 */
class Role extends Model
{
    /** Slug of the role created by the installer. */
    public const SUPER_ADMIN_SLUG = 'super-admin';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withTimestamps();
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * A super admin role implicitly holds every permission, so new permissions
     * added by a later release never leave the installation locked out.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissions->contains('name', $permission);
    }

    /**
     * True when removing this role would leave the system without any usable
     * administrative access path.
     */
    public function isLastActiveSuperAdminRole(): bool
    {
        if (! $this->isSuperAdmin()) {
            return false;
        }

        return self::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->whereKeyNot($this->getKey())
            ->doesntExist();
    }
}
