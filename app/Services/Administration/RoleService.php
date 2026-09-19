<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Enums\AuditAction;
use App\Exceptions\WorkflowViolationException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $permissionNames
     */
    public function create(array $attributes, array $permissionNames, User $actor): Role
    {
        return DB::transaction(function () use ($attributes, $permissionNames, $actor): Role {
            $role = Role::query()->create([
                'name' => $attributes['name'],
                'slug' => $this->slugFor($attributes),
                'description' => $attributes['description'] ?? null,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
            ]);

            $this->syncPermissions($role, $permissionNames);

            $this->audit->record(
                AuditAction::RoleCreated,
                $role,
                "Role {$role->name} created.",
                ['permissions' => count($permissionNames)],
                $actor,
            );

            return $role;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>|null  $permissionNames  null leaves the grants untouched
     */
    public function update(Role $role, array $attributes, ?array $permissionNames, User $actor): Role
    {
        return DB::transaction(function () use ($role, $attributes, $permissionNames, $actor): Role {
            if ($role->isSuperAdmin() && array_key_exists('is_active', $attributes) && ! $attributes['is_active']) {
                if ($role->isLastActiveSuperAdminRole()) {
                    throw WorkflowViolationException::because(
                        'This is the last active administrative role and cannot be deactivated.'
                    );
                }
            }

            $role->fill([
                'name' => $attributes['name'],
                'slug' => $this->slugFor($attributes, $role),
                'description' => $attributes['description'] ?? null,
                'is_active' => (bool) ($attributes['is_active'] ?? $role->is_active),
            ]);

            $changed = array_keys($role->getDirty());
            $role->save();

            $this->audit->record(
                AuditAction::RoleUpdated,
                $role,
                "Role {$role->name} updated.",
                ['changed' => $changed],
                $actor,
            );

            if ($permissionNames !== null) {
                $this->assignPermissions($role, $permissionNames, $actor);
            }

            return $role->refresh();
        });
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function assignPermissions(Role $role, array $permissionNames, User $actor): Role
    {
        if ($role->isSuperAdmin()) {
            throw WorkflowViolationException::because(
                'The administrative role always holds every permission and cannot be edited.'
            );
        }

        return DB::transaction(function () use ($role, $permissionNames, $actor): Role {
            $this->syncPermissions($role, $permissionNames);

            $this->audit->record(
                AuditAction::RolePermissionsAssigned,
                $role,
                "Permissions updated for role {$role->name}.",
                ['permissions' => count($permissionNames)],
                $actor,
            );

            return $role->refresh();
        });
    }

    public function delete(Role $role, User $actor): void
    {
        if ($role->isSuperAdmin()) {
            throw WorkflowViolationException::because('The administrative role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            throw WorkflowViolationException::because(
                'Reassign the users holding this role before deleting it.'
            );
        }

        $name = $role->name;
        $role->permissions()->detach();
        $role->delete();

        $this->audit->record(
            AuditAction::RoleDeleted,
            $role,
            "Role {$name} deleted.",
            [],
            $actor,
        );
    }

    /** @param array<int, string> $permissionNames */
    private function syncPermissions(Role $role, array $permissionNames): void
    {
        $ids = Permission::query()->whereIn('name', $permissionNames)->pluck('id')->all();

        $role->permissions()->sync($ids);
        $role->unsetRelation('permissions');
    }

    /** @param array<string, mixed> $attributes */
    private function slugFor(array $attributes, ?Role $role = null): string
    {
        // The seeded administrative role keeps its slug so the installer can
        // always find it again.
        if ($role?->isSuperAdmin()) {
            return $role->slug;
        }

        $slug = trim((string) ($attributes['slug'] ?? ''));

        return Str::slug($slug !== '' ? $slug : (string) $attributes['name']);
    }
}
