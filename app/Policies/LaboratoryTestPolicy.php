<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LaboratoryTest;
use App\Models\User;

class LaboratoryTestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('laboratory.test.view');
    }

    public function view(User $user, LaboratoryTest $test): bool
    {
        return $user->hasPermission('laboratory.test.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('laboratory.test.create');
    }

    public function update(User $user, LaboratoryTest $test): bool
    {
        return $user->hasPermission('laboratory.test.update');
    }

    /**
     * Catalogue entries referenced by laboratory records are retired by
     * deactivation, never deleted, so historical reports stay intact.
     */
    public function delete(User $user, LaboratoryTest $test): bool
    {
        return $user->hasPermission('laboratory.test.delete')
            && ! $test->isReferencedByLaboratoryRecords()
            && $test->panels()->doesntExist();
    }

    public function activate(User $user, LaboratoryTest $test): bool
    {
        return $user->hasPermission('laboratory.test.activate') && ! $test->is_active;
    }

    public function deactivate(User $user, LaboratoryTest $test): bool
    {
        return $user->hasPermission('laboratory.test.deactivate') && $test->is_active;
    }
}
