<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\StaffIdentityException;
use App\Models\Staff;
use App\Models\User;
use App\Support\ActorIdentity;
use Illuminate\Support\Facades\Auth;

/**
 * The single authoritative answer to "who is performing this action?".
 *
 * The chain is fixed and runs entirely server-side:
 *
 *     authenticated session -> User -> User.staff_id -> Staff -> ActorIdentity
 *
 * Nothing the client sends participates. A request may contain
 * `validated_by_staff_id`, `approved_by` or any similar field; none of them are
 * read here, and none can influence the result.
 *
 * This class exists precisely once. Controllers, services, policies, views,
 * reports and the audit trail all consume it rather than walking
 * User -> Staff themselves, so there is one place where the rules about
 * suspended staff and missing records are applied.
 *
 * Every failure is explicit. There is no fallback to `users.name`: a partially
 * resolved actor on a clinical record is a defect that surfaces months later on
 * a printed report, so the operation fails instead.
 */
class AuthenticatedStaffResolver
{
    /** Memoised per request; the identity cannot change mid-request. */
    private ?ActorIdentity $resolved = null;

    private ?int $resolvedForUserId = null;

    /**
     * The current actor.
     *
     * @throws StaffIdentityException when no session, no staff link, a missing
     *                                staff record, or a status that forbids work
     */
    public function resolve(?User $user = null): ActorIdentity
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            throw StaffIdentityException::noAuthenticatedUser();
        }

        if ($this->resolved !== null && $this->resolvedForUserId === $user->getKey()) {
            return $this->resolved;
        }

        $identity = ActorIdentity::fromStaff($user, $this->staffFor($user));

        $this->resolved = $identity;
        $this->resolvedForUserId = $user->getKey();

        return $identity;
    }

    /**
     * The current actor, or the system actor when nothing is signed in.
     *
     * For work that legitimately runs both ways — a command that can be invoked
     * from the console or from a controller. It does NOT soften the rules: a
     * signed-in user whose staff record forbids work still throws.
     */
    public function resolveOrSystem(?string $systemDetail = null): ActorIdentity
    {
        if (! Auth::check()) {
            return ActorIdentity::system($systemDetail);
        }

        return $this->resolve();
    }

    /**
     * Whether the current user can act, without throwing.
     *
     * For deciding what to show. Never use it to decide whether to perform an
     * action — call resolve() for that, so the check and the action cannot
     * drift apart.
     */
    public function canAct(?User $user = null): bool
    {
        try {
            $this->resolve($user);

            return true;
        } catch (StaffIdentityException) {
            return false;
        }
    }

    /**
     * The staff record behind an account, with its status checked.
     *
     * @throws StaffIdentityException
     */
    public function staffFor(User $user): Staff
    {
        if ($user->staff_id === null) {
            throw StaffIdentityException::missingStaffRecord($user->email);
        }

        $staff = $user->relationLoaded('staff')
            ? $user->getRelation('staff')
            : Staff::query()->find($user->staff_id);

        if (! $staff instanceof Staff) {
            throw StaffIdentityException::staffNotFound($user->staff_id);
        }

        if (! $staff->permitsSystemAccess()) {
            throw StaffIdentityException::statusForbidsWork(
                $staff->staff_id,
                $staff->status->label(),
            );
        }

        return $staff;
    }

    /** Clears the memoised identity; used when a request changes the staff record. */
    public function forget(): void
    {
        $this->resolved = null;
        $this->resolvedForUserId = null;
    }
}
