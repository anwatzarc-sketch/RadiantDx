<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LaboratoryResult;
use App\Models\User;

/**
 * Holding a permission is necessary but not sufficient: the state of the record
 * decides whether the operation is legal at all. A validated result is
 * finalised, and no permission reopens it for ordinary editing.
 */
class LaboratoryResultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('laboratory.result.view');
    }

    public function view(User $user, LaboratoryResult $result): bool
    {
        return $user->hasPermission('laboratory.result.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('laboratory.result.create');
    }

    /**
     * Ordinary result entry. Closed once the result is validated: a correction
     * has to go through unvalidation so the change leaves an audit trail.
     */
    public function update(User $user, LaboratoryResult $result): bool
    {
        return $user->hasPermission('laboratory.result.update')
            && $result->isEditable()
            && ! $result->requisition->isCancelled();
    }

    public function delete(User $user, LaboratoryResult $result): bool
    {
        return $user->hasPermission('laboratory.result.delete') && ! $result->isValidated();
    }

    /** Only a complete result can be validated. */
    public function validate(User $user, LaboratoryResult $result): bool
    {
        return $user->hasPermission('laboratory.result.validate')
            && $result->canBeValidated()
            && ! $result->requisition->isCancelled();
    }

    public function unvalidate(User $user, LaboratoryResult $result): bool
    {
        return $user->hasPermission('laboratory.result.unvalidate') && $result->isValidated();
    }

    /*
     * Checked both against one result and against the class: the combined
     * requisition report covers many results at once, so it asks whether the
     * user may print at all before it knows which results it will include.
     * The model is optional because printing turns on the permission alone.
     */
    public function print(User $user, ?LaboratoryResult $result = null): bool
    {
        return $user->hasPermission('laboratory.result.print');
    }
}
