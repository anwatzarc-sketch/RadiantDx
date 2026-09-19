<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Besides the permission checks, these rules stop the administration from being
 * locked out: the last remaining administrator cannot be removed, deactivated
 * or moved off its role, and nobody can delete or deactivate themselves.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.update');
    }

    public function delete(User $user, User $target): bool
    {
        if (! $user->hasPermission('users.delete')) {
            return false;
        }

        if ($user->is($target)) {
            return false;
        }

        return ! $target->isLastActiveAdministrator();
    }

    public function activate(User $user, User $target): bool
    {
        return $user->hasPermission('users.activate') && ! $target->is_active;
    }

    public function deactivate(User $user, User $target): bool
    {
        if (! $user->hasPermission('users.deactivate') || ! $target->is_active) {
            return false;
        }

        if ($user->is($target)) {
            return false;
        }

        return ! $target->isLastActiveAdministrator();
    }

    /**
     * Whether the role field may be changed on this account. Moving the last
     * administrator to another role would remove the final access path.
     */
    public function changeRole(User $user, User $target): bool
    {
        return $user->hasPermission('users.update') && ! $target->isLastActiveAdministrator();
    }
}
