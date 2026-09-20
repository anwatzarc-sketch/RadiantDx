<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;

/**
 * Builds signed-in accounts backed by staff records.
 *
 * Since users.staff_id became required, every test account needs a staff record
 * behind it. Doing that in one place keeps the tests readable and means a test
 * cannot accidentally assert against an account shape production can't produce.
 */
trait CreatesStaffUsers
{
    /**
     * A user holding exactly the named permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(array $permissions, ?Staff $staff = null): User
    {
        $role = Role::query()->create([
            'name' => 'Test Role '.fake()->unique()->numberBetween(1, 999999),
            'slug' => 'test-role-'.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['label' => $name, 'module' => 'Test', 'module_order' => 1, 'display_order' => 1],
            );

            $role->permissions()->attach($permission);
        }

        return $this->userForRole($role, $staff);
    }

    /** A user holding every permission. */
    protected function superAdmin(?Staff $staff = null): User
    {
        $role = Role::query()->create([
            'name' => 'Super Admin '.fake()->unique()->numberBetween(1, 999999),
            'slug' => 'super-admin-'.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
        ]);

        // is_super_admin is deliberately not mass assignable in production, so
        // it is set explicitly here rather than passed to create().
        $role->forceFill(['is_super_admin' => true])->save();

        return $this->userForRole($role, $staff);
    }

    private function userForRole(Role $role, ?Staff $staff): User
    {
        $staff ??= Staff::factory()->create();

        $user = new User([
            'name' => $staff->full_name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->getKey(),
            'is_active' => true,
            'must_change_password' => false,
        ]);

        // Not fillable, exactly as in production.
        $user->staff_id = $staff->getKey();
        $user->save();

        return $user;
    }
}
