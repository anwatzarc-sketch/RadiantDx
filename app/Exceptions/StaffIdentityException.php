<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an authenticated account cannot be resolved to a usable
 * professional identity.
 *
 * Every one of these is explicit and fails the operation. There is deliberately
 * no fallback to `users.name`: a laboratory record attributed to a partially
 * resolved actor is worse than an operation that refuses to proceed, because
 * the first is discovered months later on a printed report.
 */
class StaffIdentityException extends RuntimeException
{
    public static function missingStaffRecord(string $email): self
    {
        return new self(
            "The account {$email} is not linked to a staff record, so it cannot be "
            .'recorded as the person performing this action. An administrator must link it.'
        );
    }

    public static function staffNotFound(int $staffId): self
    {
        return new self(
            "Staff record {$staffId} is referenced by an account but no longer exists."
        );
    }

    public static function statusForbidsWork(string $staffNumber, string $status): self
    {
        return new self(
            "Staff {$staffNumber} is {$status} and may not perform laboratory work."
        );
    }

    public static function noAuthenticatedUser(): self
    {
        return new self(
            'No authenticated user is available to resolve as the actor. Work that runs '
            .'outside a session must use ActorIdentity::system().'
        );
    }
}
