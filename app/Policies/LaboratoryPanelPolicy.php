<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LaboratoryPanel;
use App\Models\User;

class LaboratoryPanelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('laboratory.panel.view');
    }

    public function view(User $user, LaboratoryPanel $panel): bool
    {
        return $user->hasPermission('laboratory.panel.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('laboratory.panel.create');
    }

    public function update(User $user, LaboratoryPanel $panel): bool
    {
        return $user->hasPermission('laboratory.panel.update');
    }

    public function delete(User $user, LaboratoryPanel $panel): bool
    {
        return $user->hasPermission('laboratory.panel.delete')
            && ! $panel->isReferencedByLaboratoryRecords();
    }

    public function activate(User $user, LaboratoryPanel $panel): bool
    {
        return $user->hasPermission('laboratory.panel.activate') && ! $panel->is_active;
    }

    public function deactivate(User $user, LaboratoryPanel $panel): bool
    {
        return $user->hasPermission('laboratory.panel.deactivate') && $panel->is_active;
    }
}
