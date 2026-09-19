<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account created with a temporary password is held on the profile security
 * page until a new password is chosen.
 */
class EnsurePasswordIsChanged
{
    /** Routes that must stay reachable so the password can actually be changed. */
    private const ALLOWED_ROUTES = [
        'profile.edit',
        'profile.update',
        'profile.password.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && $user->must_change_password
            && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)
        ) {
            return redirect()
                ->route('profile.edit')
                ->with('warning', 'Choose a new password before continuing.');
        }

        return $next($request);
    }
}
