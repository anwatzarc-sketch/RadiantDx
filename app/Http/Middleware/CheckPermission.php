<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route level permission gate.
 *
 * Hiding an action in the interface is a usability decision; this middleware is
 * what actually protects the operation. Every route that performs a protected
 * operation declares its permission, and unauthorised direct requests are
 * refused with 403 regardless of what the navigation showed.
 */
class CheckPermission
{
    /**
     * @param  string  ...$permissions  the request is allowed when the user holds any of them
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isActive()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($permissions !== [] && ! $user->hasAnyPermission($permissions)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
