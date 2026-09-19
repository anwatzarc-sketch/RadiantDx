<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LaboratoryTestParameter;
use App\Models\User;

class LaboratoryTestParameterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('laboratory.parameter.view');
    }

    public function view(User $user, LaboratoryTestParameter $parameter): bool
    {
        return $user->hasPermission('laboratory.parameter.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('laboratory.parameter.create');
    }

    public function update(User $user, LaboratoryTestParameter $parameter): bool
    {
        return $user->hasPermission('laboratory.parameter.update');
    }

    /** A parameter that has already been reported on is retired, not deleted. */
    public function delete(User $user, LaboratoryTestParameter $parameter): bool
    {
        return $user->hasPermission('laboratory.parameter.delete')
            && ! $parameter->isReferencedByLaboratoryRecords();
    }

    public function activate(User $user, LaboratoryTestParameter $parameter): bool
    {
        return $user->hasPermission('laboratory.parameter.activate') && ! $parameter->is_active;
    }

    public function deactivate(User $user, LaboratoryTestParameter $parameter): bool
    {
        return $user->hasPermission('laboratory.parameter.deactivate') && $parameter->is_active;
    }
}
