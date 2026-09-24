<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LaboratoryReferenceRange;
use App\Models\User;

/**
 * Reference ranges are part of a parameter's definition, so they are guarded
 * by the parameter permissions rather than permissions of their own.
 *
 * Verifying is an update: it is the laboratory director saying "this range
 * is right", which is the same authority as editing it.
 */
class LaboratoryReferenceRangePolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('laboratory.parameter.update');
    }

    public function update(User $user, LaboratoryReferenceRange $range): bool
    {
        return $user->hasPermission('laboratory.parameter.update');
    }

    public function verify(User $user, LaboratoryReferenceRange $range): bool
    {
        return $user->hasPermission('laboratory.parameter.update') && $range->is_placeholder;
    }

    public function activate(User $user, LaboratoryReferenceRange $range): bool
    {
        return $user->hasPermission('laboratory.parameter.activate') && ! $range->is_active;
    }

    public function deactivate(User $user, LaboratoryReferenceRange $range): bool
    {
        return $user->hasPermission('laboratory.parameter.deactivate') && $range->is_active;
    }

    /**
     * Deleting is soft: results that used the range keep their own copy of
     * its values, so nothing that was reported is lost.
     */
    public function delete(User $user, LaboratoryReferenceRange $range): bool
    {
        return $user->hasPermission('laboratory.parameter.delete');
    }
}
