<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

/**
 * Besides the permission checks, these rules protect the identity chain: a
 * staff member with laboratory history cannot be deleted, and nobody can
 * suspend the staff record behind their own account and lock themselves out
 * mid-session.
 */
class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.view');
    }

    public function view(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('staff.create');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.update');
    }

    /**
     * Deletion is for records created in error only. Once someone has acted on
     * a laboratory record they are retired instead, so reports issued in their
     * name keep resolving.
     */
    public function delete(User $user, Staff $staff): bool
    {
        if (! $user->hasPermission('staff.delete')) {
            return false;
        }

        if ($user->staff_id === $staff->getKey()) {
            return false;
        }

        return ! $staff->hasLaboratoryHistory();
    }

    public function changeStatus(User $user, Staff $staff): bool
    {
        if (! $user->hasPermission('staff.status.manage')) {
            return false;
        }

        // Suspending the staff record behind your own account would end your
        // own session on the next request.
        return $user->staff_id !== $staff->getKey();
    }

    /** Creating the system account for a staff record. */
    public function manageAccount(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.account.manage');
    }

    /**
     * Uploading or removing a profile photograph.
     *
     * Two ways in: the general permission for administrators, or the
     * self-service one for changing your own picture. The second is checked
     * against the staff record behind the account, not against anything the
     * request supplied.
     */
    public function managePhoto(User $user, Staff $staff): bool
    {
        if ($user->hasPermission('staff.photo.manage')) {
            return true;
        }

        return $user->staff_id === $staff->getKey()
            && $user->hasPermission('staff.photo.manage.self');
    }

    /** Licensing, registration and practice details. */
    public function managePhysicianProfile(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.physician.manage');
    }

    public function manageQualifications(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.qualification.manage');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('staff.import');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('staff.export');
    }

    public function viewActivity(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.activity.view');
    }
}
