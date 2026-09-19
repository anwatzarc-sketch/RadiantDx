<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised when an operation is refused by a business rule rather than by
 * authorisation or input validation, for example submitting an empty
 * requisition or validating a result that is not complete.
 *
 * Services throw this so the rule is enforced no matter which controller,
 * console command or test calls them.
 */
class WorkflowViolationException extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], Response::HTTP_CONFLICT);
        }

        return back()->with('error', $this->getMessage());
    }
}
