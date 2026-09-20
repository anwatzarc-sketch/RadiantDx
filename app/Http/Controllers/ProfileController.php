<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\Profile\UpdateOwnStaffProfileRequest;
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
            'user' => $user->load(['role.permissions', 'staff.department', 'staff.unit', 'staff.qualifications']),
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

    /**
     * Updates the part of your own staff record you are allowed to change.
     *
     * The request object owns the boundary: it validates only the
     * self-editable attributes and hands back only those, so nothing posted
     * alongside them — speciality, department, status, employee ID, the staff
     * identifier — can reach the model.
     */
    public function updateStaffProfile(UpdateOwnStaffProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $staff = $user->staff;

        if ($staff === null) {
            return back()->with('error', 'Your account is not linked to a staff record.');
        }

        $staff->fill($request->selfEditableAttributes());
        $changed = array_keys($staff->getDirty());
        $staff->save();

        $this->audit->record(
            AuditAction::StaffUpdated,
            $staff,
            "{$user->email} updated their own contact details.",
            ['changed' => $changed, 'self_service' => true],
            $user,
        );

        return back()->with('success', 'Your details have been updated.');
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
