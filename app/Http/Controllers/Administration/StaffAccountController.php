<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\StaffAccountRequest;
use App\Models\Staff;
use App\Services\Administration\StaffService;
use Illuminate\Http\RedirectResponse;

/**
 * Creating the system account for a staff record.
 *
 * The staff record is a route parameter, so the association is established from
 * where the administrator navigated rather than from anything they typed. This
 * flow has no field for choosing which staff record an account belongs to, and
 * StaffAccountRequest strips one if a crafted request supplies it.
 *
 * The users create form reaches the same outcome from the other direction, with
 * a validated staff_id field. That is deliberate -- users.staff_id is mandatory
 * and that form could not otherwise create anything -- but it is the looser of
 * the two paths, so prefer this one when starting from a person.
 */
class StaffAccountController extends Controller
{
    public function __construct(private readonly StaffService $staff) {}

    public function store(StaffAccountRequest $request, Staff $staff): RedirectResponse
    {
        $this->authorize('manageAccount', $staff);

        $user = $this->staff->createAccountFor(
            $staff,
            $request->accountAttributes(),
            $request->user(),
        );

        return redirect()
            ->route('administration.staff.show', $staff)
            ->with('success', "Account {$user->email} created for {$staff->full_name}. They will choose a new password at first sign in.");
    }
}
