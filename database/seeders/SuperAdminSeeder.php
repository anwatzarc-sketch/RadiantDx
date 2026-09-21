<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StaffStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\Administration\StaffNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates the one role and the one account an installation starts with.
 *
 * Nothing operational is seeded: laboratory roles and their users are created
 * from the UI by the administrator.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = (string) config('laboratory.super_admin.name');
        $email = mb_strtolower(trim((string) config('laboratory.super_admin.email')));
        $password = (string) config('laboratory.super_admin.password');

        if ($email === '') {
            throw new RuntimeException('SUPER_ADMIN_EMAIL must be set before seeding.');
        }

        $generated = false;

        if ($password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'SUPER_ADMIN_PASSWORD must be set before seeding in production.'
                );
            }

            $password = Str::password(16);
            $generated = true;
        }

        DB::transaction(function () use ($name, $email, $password): void {
            $role = Role::query()->firstOrNew(['slug' => Role::SUPER_ADMIN_SLUG]);

            $role->fill([
                'name' => 'Super Admin',
                'description' => 'Full administrative access to every part of the system.',
                'is_active' => true,
            ]);
            $role->is_super_admin = true;
            $role->save();

            // Grant the full catalogue explicitly so the assignment is visible on
            // the role screen. A super admin role also resolves permissions
            // implicitly, which keeps the installation usable if a future release
            // introduces a permission before this seeder is run again.
            $role->permissions()->sync(Permission::query()->pluck('id')->all());

            $user = User::withTrashed()->firstOrNew(['email' => $email]);

            $isNew = ! $user->exists;

            // Every account needs a staff record behind it: users.staff_id is
            // required, and the staff record is what the laboratory screens and
            // the printed report resolve an actor's identity through.
            $staff = $this->staffFor($user, $name, $email);

            $user->fill([
                'role_id' => $role->id,
                'is_active' => true,
            ]);

            // The name is governed by the staff record, not by configuration.
            // Re-asserting the configured value on every run would silently undo
            // a name corrected through Staff Management and leave the two out of
            // step — which is what the laboratory reports read.
            $user->name = $staff->full_name;
            $user->staff_id = $staff->getKey();

            $user->deleted_at = null;

            // An existing administrator keeps the password already in use; only a
            // freshly created account receives the configured one.
            if ($isNew) {
                $user->password = Hash::make($password);
                $user->must_change_password = true;
                $user->email_verified_at = now();
            }

            $user->save();
        });

        $this->command?->info("Super Admin role and user ready ({$email}).");

        if ($generated) {
            $this->command?->warn("Generated temporary password: {$password}");
            $this->command?->warn('Change it immediately after the first sign in.');
        }
    }

    /**
     * The staff record behind the super admin account, created on first seed.
     *
     * An existing record is left alone apart from ensuring it is active: its
     * name may well have been corrected since the installation was first
     * seeded, and configuration must not overwrite that.
     */
    private function staffFor(User $user, string $name, string $email): Staff
    {
        $staff = $user->staff_id !== null
            ? Staff::query()->find($user->staff_id)
            : Staff::query()->where('email', $email)->first();

        if ($staff instanceof Staff) {
            if (! $staff->permitsSystemAccess()) {
                $staff->status = StaffStatus::Active;
                $staff->save();
            }

            return $staff;
        }

        $staff = new Staff([
            'full_name' => $name,
            'email' => $email,
            'status' => StaffStatus::Active->value,
            // Professional details are not invented for a bootstrap account.
            'needs_review' => true,
        ]);

        $staff->staff_code = app(StaffNumberGenerator::class)->next();
        $staff->save();

        return $staff;
    }
}
