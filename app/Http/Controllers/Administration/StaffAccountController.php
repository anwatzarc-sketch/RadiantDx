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
 * where the administrator navigated rather than from anything they typed. There
 * is no field anywhere in this flow for choosing which staff record an account
 * belongs to.
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
