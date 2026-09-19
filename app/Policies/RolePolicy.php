<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * The seeded administrative role is protected: it cannot be deleted, cannot be
 * deactivated while it is the last one, and its permission grants cannot be
 * stripped. That keeps the installation from reaching an unusable state.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->hasPermission('roles.delete')) {
            return false;
        }

        if ($role->isSuperAdmin()) {
            return false;
        }

        return $role->users()->doesntExist();
    }

    public function deactivate(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.update')
            && $role->is_active
            && ! $role->isLastActiveSuperAdminRole();
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.assign_permissions') && ! $role->isSuperAdmin();
    }
}
