<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Enums\AuditAction;
use App\Enums\StaffStatus;
use App\Exceptions\WorkflowViolationException;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Staff record lifecycle.
 *
 * Two rules run through everything here:
 *
 *  - the staff identifier is issued by the server and never accepted from a
 *    caller, whether that caller is a form, an import row or a console session;
 *  - a staff member with laboratory history is retired, never deleted, so the
 *    reports issued in their name keep resolving to a real person.
 */
class StaffService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StaffNumberGenerator $numbers,
    ) {}

    /**
     * Creates a staff record.
     *
     * $attributes is validated form data and deliberately never contains
     * `staff_code` — the identifier is issued here, inside the transaction, and
     * retried if a concurrent request takes the number first.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): Staff
    {
        return DB::transaction(fn (): Staff => $this->numbers->nextWithRetry(
            function (string $staffNumber) use ($attributes, $actor): Staff {
                $staff = new Staff($this->assignable($attributes));
                $staff->staff_code = $staffNumber;
                $staff->save();

                $this->audit->record(
                    AuditAction::StaffCreated,
                    $staff,
                    "Staff {$staff->staff_code} ({$staff->full_name}) created.",
                    ['profession' => $staff->profession?->value],
                    $actor,
                );

                return $staff;
            },
        ));
    }

    /**
     * Updates a staff record.
     *
     * A status change is audited as its own event, because "who was suspended
     * and when" is a question that gets asked on its own.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Staff $staff, array $attributes, User $actor): Staff
    {
        return DB::transaction(function () use ($staff, $attributes, $actor): Staff {
            $previousStatus = $staff->status;

            $staff->fill($this->assignable($attributes));

            // Completing a record by hand clears the flag the backfill or an
            // import set on it.
            if ($staff->isDirty() && $staff->needs_review && $this->isSufficientlyComplete($staff)) {
                $staff->needs_review = false;
            }

            $changed = array_keys($staff->getDirty());
            $nameChanged = $staff->isDirty('full_name');
            $previousName = $staff->getOriginal('full_name');

            $staff->save();

            if ($nameChanged) {
                $this->cascadeNameToAccount($staff, $previousName, $actor);
            }

            $this->audit->record(
                AuditAction::StaffUpdated,
                $staff,
                "Staff {$staff->staff_code} updated.",
                ['changed' => $changed],
                $actor,
            );

            if ($staff->status !== $previousStatus) {
                $this->recordStatusChange($staff, $previousStatus, $actor);
            }

            return $staff;
        });
    }

    /**
     * Changes standing.
     *
     * Suspending or retiring somebody also closes their account: leaving a
     * former member of staff able to sign in because only the staff record was
     * updated would be a hole that is easy to miss.
     */
    public function setStatus(Staff $staff, StaffStatus $status, User $actor): Staff
    {
        return DB::transaction(function () use ($staff, $status, $actor): Staff {
            $previous = $staff->status;

            if ($previous === $status) {
                return $staff;
            }

            $staff->status = $status;
            $staff->save();

            $this->recordStatusChange($staff, $previous, $actor);

            if (! $status->permitsSystemAccess()) {
                $this->disableAccountFor($staff, $actor);
            }

            return $staff;
        });
    }

    /**
     * Creates the system account for a staff record.
     *
     * The staff record comes from the route, not the request body: the caller
     * has already navigated to a specific staff profile, so the association is
     * established from trusted context. An administrator is never asked to type
     * or pick a staff identifier, and a `staff_code` in the payload is ignored.
     *
     * @param  array<string, mixed>  $attributes  username/email, role, status, password
     */
    public function createAccountFor(Staff $staff, array $attributes, User $actor): User
    {
        if ($staff->user !== null) {
            throw WorkflowViolationException::because(
                "Staff {$staff->staff_code} already has an account ({$staff->user->email})."
            );
        }

        if (! $staff->permitsSystemAccess()) {
            throw WorkflowViolationException::because(
                "Staff {$staff->staff_code} is {$staff->status->label()} and cannot be given an account."
            );
        }

        return DB::transaction(function () use ($staff, $attributes, $actor): User {
            $user = new User([
                // The account's display name mirrors the staff record rather
                // than being typed again, so the two cannot disagree.
                'name' => $staff->full_name,
                'email' => mb_strtolower(trim((string) $attributes['email'])),
                'role_id' => $attributes['role_id'] ?? null,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'must_change_password' => true,
            ]);

            // Not fillable, and not sourced from the request.
            $user->staff_id = $staff->getKey();
            $user->password = Hash::make((string) $attributes['password']);
            $user->email_verified_at = now();
            $user->save();

            $this->audit->record(
                AuditAction::StaffAccountCreated,
                $staff,
                "Account {$user->email} created for staff {$staff->staff_code}.",
                ['role' => $user->roleName(), 'user_id' => $user->getKey()],
                $actor,
            );

            return $user;
        });
    }

    /**
     * Removes a staff record.
     *
     * Refused once the person has laboratory history: a validated result must
     * keep resolving to whoever validated it. Retiring them is the supported
     * path and is what the exception says.
     */
    public function delete(Staff $staff, User $actor): void
    {
        if ($staff->hasLaboratoryHistory()) {
            throw WorkflowViolationException::because(
                "Staff {$staff->staff_code} has laboratory activity on record and cannot be deleted. "
                .'Set their status to Former instead, which keeps historical reports intact.'
            );
        }

        DB::transaction(function () use ($staff, $actor): void {
            $staffNumber = $staff->staff_code;
            $name = $staff->full_name;

            $this->disableAccountFor($staff, $actor);
            $staff->delete();

            $this->audit->record(
                AuditAction::StaffUpdated,
                $staff,
                "Staff {$staffNumber} ({$name}) deleted.",
                ['deleted' => true],
                $actor,
            );
        });
    }

    /**
     * Only the fields a caller is allowed to set.
     *
     * An explicit allow-list rather than a reliance on $fillable, so a field
     * added to the model later is not silently writable from every caller.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function assignable(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip([
            'full_name', 'gender', 'date_of_birth',
            'phone', 'email', 'address',
            'title', 'profession', 'speciality', 'sub_speciality',
            'department_id', 'unit_id', 'position',
            'professional_license', 'license_expiry',
            'employee_id', 'employment_type', 'status', 'joined_on', 'supervisor_id',
        ]));
    }

    /** A record is complete enough to clear review once it names a profession. */
    private function isSufficientlyComplete(Staff $staff): bool
    {
        return $staff->profession !== null;
    }

    private function recordStatusChange(Staff $staff, StaffStatus $previous, User $actor): void
    {
        $this->audit->record(
            AuditAction::StaffStatusChanged,
            $staff,
            "Staff {$staff->staff_code} moved from {$previous->label()} to {$staff->status->label()}.",
            ['from' => $previous->value, 'to' => $staff->status->value],
            $actor,
        );
    }

    /**
     * Carries a corrected staff name through to the account, and so to every
     * screen that names the person who acted.
     *
     * The requisition and result screens, and the printed report, all read the
     * actor's name through the account (`validatedBy->name`, `performedBy->name`
     * and so on). Without this the two diverge the first time a name is
     * corrected: the staff record reads "Khalid Ahmed" while every report still
     * says whatever the account was created with.
     *
     * One row is written, not thousands — the laboratory records hold a foreign
     * key, so correcting the name they resolve through corrects all of them at
     * once.
     *
     * NOTE FOR PHASE 4. This is the right behaviour while identity is resolved
     * by live join, which is how it works today. Once Phase 4 writes immutable
     * actor snapshots at validation and report issue, records already issued
     * will stop following later name changes by design — a report reprinted
     * years later must match the copy that was filed. Work in progress and the
     * staff directory will keep following the live name. The two are not in
     * conflict; they answer different questions.
     */
    private function cascadeNameToAccount(Staff $staff, ?string $previousName, User $actor): void
    {
        $user = $staff->user;

        if ($user === null || $user->name === $staff->full_name) {
            return;
        }

        $user->name = $staff->full_name;
        $user->save();

        $this->audit->record(
            AuditAction::StaffUpdated,
            $staff,
            "Account name for staff {$staff->staff_code} updated from \"{$previousName}\" to \"{$staff->full_name}\".",
            [
                'cascade' => 'account_name',
                'from' => $previousName,
                'to' => $staff->full_name,
                'user_id' => $user->getKey(),
            ],
            $actor,
        );
    }

    /** Closes the account behind a staff record, if there is one still open. */
    private function disableAccountFor(Staff $staff, User $actor): void
    {
        $user = $staff->user;

        if ($user === null || ! $user->is_active) {
            return;
        }

        $user->is_active = false;
        $user->save();

        $this->audit->record(
            AuditAction::StaffAccountDisabled,
            $staff,
            "Account {$user->email} disabled because staff {$staff->staff_code} is {$staff->status->label()}.",
            ['user_id' => $user->getKey()],
            $actor,
        );
    }
}
