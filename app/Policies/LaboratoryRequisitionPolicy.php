<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RequisitionStatus;
use App\Models\LaboratoryRequisition;
use App\Models\User;

class LaboratoryRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('laboratory.requisition.view');
    }

    public function view(User $user, LaboratoryRequisition $requisition): bool
    {
        return $user->hasPermission('laboratory.requisition.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('laboratory.requisition.create');
    }

    /** Clinical and catalogue details are open for editing only while in draft. */
    public function update(User $user, LaboratoryRequisition $requisition): bool
    {
        return $user->hasPermission('laboratory.requisition.update') && $requisition->isEditable();
    }

    /** A requisition that already carries laboratory work is never removed. */
    public function delete(User $user, LaboratoryRequisition $requisition): bool
    {
        return $user->hasPermission('laboratory.requisition.delete')
            && $requisition->status === RequisitionStatus::Draft
            && $requisition->results()->doesntExist();
    }

    public function submit(User $user, LaboratoryRequisition $requisition): bool
    {
        return $user->hasPermission('laboratory.requisition.submit') && $requisition->canBeSubmitted();
    }

    public function cancel(User $user, LaboratoryRequisition $requisition): bool
    {
        return $user->hasPermission('laboratory.requisition.cancel') && $requisition->canBeCancelled();
    }

    /**
     * Moving the specimen through collection, processing and completion. The
     * laboratory drives this from the requisition screen, so it is guarded by
     * the same permission as other requisition updates.
     */
    public function advance(User $user, LaboratoryRequisition $requisition): bool
    {
        if (! $user->hasPermission('laboratory.requisition.update')) {
            return false;
        }

        return $requisition->status->allowedTransitions() !== [];
    }
}
