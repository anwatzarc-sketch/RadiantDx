<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // A fresh session id after authentication closes off session fixation.
        $request->session()->regenerate();

        $user = $request->user();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->audit->record(
            AuditAction::UserLoggedIn,
            $user,
            "{$user->email} signed in.",
            [],
            $user,
        );

        if ($user->must_change_password) {
            return redirect()
                ->route('profile.edit')
                ->with('warning', 'Choose a new password before continuing.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $this->audit->record(
                AuditAction::UserLoggedOut,
                $user,
                "{$user->email} signed out.",
                [],
                $user,
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
