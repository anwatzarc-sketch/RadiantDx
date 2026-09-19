<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Enums\AuditAction;
use App\Exceptions\WorkflowViolationException;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($attributes, $actor): User {
            $user = new User([
                'name' => $attributes['name'],
                'email' => mb_strtolower(trim((string) $attributes['email'])),
                'role_id' => $attributes['role_id'] ?? null,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'must_change_password' => true,
            ]);

            $user->password = Hash::make((string) $attributes['password']);
            $user->email_verified_at = now();
            $user->save();

            $this->audit->record(
                AuditAction::UserCreated,
                $user,
                "User {$user->email} created.",
                ['role' => $user->roleName()],
                $actor,
            );

            return $user;
        });
    }

    /**
     * Updates an account. The password is only touched when a new one was
     * supplied, and it is never echoed back anywhere.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($user, $attributes, $actor): User {
            $user->fill([
                'name' => $attributes['name'],
                'email' => mb_strtolower(trim((string) $attributes['email'])),
            ]);

            if (array_key_exists('role_id', $attributes)) {
                $this->assertRoleChangeIsSafe($user, $attributes['role_id']);
                $user->role_id = $attributes['role_id'];
            }

            $passwordChanged = false;

            if (! empty($attributes['password'])) {
                $user->password = Hash::make((string) $attributes['password']);
                $user->must_change_password = true;
                $passwordChanged = true;
            }

            $changed = array_keys($user->getDirty());
            $user->save();

            $this->audit->record(
                AuditAction::UserUpdated,
                $user,
                "User {$user->email} updated.",
                ['changed' => array_values(array_diff($changed, ['password']))],
                $actor,
            );

            if ($passwordChanged) {
                $this->audit->record(
                    AuditAction::UserPasswordChanged,
                    $user,
                    "Password reset for {$user->email}.",
                    [],
                    $actor,
                );
            }

            return $user;
        });
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        if (! $active) {
            if ($actor->is($user)) {
                throw WorkflowViolationException::because('You cannot deactivate your own account.');
            }

            if ($user->isLastActiveAdministrator()) {
                throw WorkflowViolationException::because(
                    'This is the last active administrator. Deactivating it would lock the system.'
                );
            }
        }

        $user->is_active = $active;
        $user->save();

        $this->audit->record(
            $active ? AuditAction::UserActivated : AuditAction::UserDeactivated,
            $user,
            "User {$user->email} ".($active ? 'activated.' : 'deactivated.'),
            [],
            $actor,
        );

        return $user;
    }

    public function delete(User $user, User $actor): void
    {
        if ($actor->is($user)) {
            throw WorkflowViolationException::because('You cannot delete your own account.');
        }

        if ($user->isLastActiveAdministrator()) {
            throw WorkflowViolationException::because(
                'This is the last active administrator and cannot be deleted.'
            );
        }

        $email = $user->email;
        $user->delete();

        $this->audit->record(
            AuditAction::UserDeleted,
            $user,
            "User {$email} deleted.",
            [],
            $actor,
        );
    }

    public function changeOwnPassword(User $user, string $password): void
    {
        $user->password = Hash::make($password);
        $user->must_change_password = false;
        $user->save();

        $this->audit->record(
            AuditAction::UserPasswordChanged,
            $user,
            "{$user->email} changed their own password.",
            [],
            $user,
        );
    }

    /** Moving the last administrator off its role would remove the final access path. */
    private function assertRoleChangeIsSafe(User $user, ?int $roleId): void
    {
        if ($user->role_id === $roleId) {
            return;
        }

        if ($user->isLastActiveAdministrator()) {
            throw WorkflowViolationException::because(
                'This is the last active administrator. Assign another administrator before changing this role.'
            );
        }
    }
}
