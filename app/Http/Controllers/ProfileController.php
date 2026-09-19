<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\AuditLog;
use App\Services\Administration\UserService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user->load('role.permissions'),
            'activity' => AuditLog::query()
                ->where('user_id', $user->getKey())
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        $this->audit->record(AuditAction::UserUpdated, $user, "{$user->email} updated their profile.", [], $user);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->users->changeOwnPassword($request->user(), (string) $request->validated('password'));

        // A new password invalidates other sessions for this account.
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password changed.');
    }
}
