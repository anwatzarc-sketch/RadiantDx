<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Enums\AuditAction;
use App\Enums\Profession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\PhysicianProfileRequest;
use App\Http\Requests\Administration\StaffQualificationRequest;
use App\Models\Department;
use App\Models\Staff;
use App\Models\StaffQualification;
use App\Services\Administration\LicenseStatusDeriver;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The physician directory and the licensing/practice section of a staff record.
 *
 * There is no physician entity. Everything here reads and writes the staff
 * record, so a physician has one identity and one name on a report.
 */
class PhysicianController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LicenseStatusDeriver $licences,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        $physicians = Staff::query()
            ->with(['department:id,name', 'user:id,staff_id,email'])
            // The directory is of people who practise under a licence, which is
            // a property of their profession rather than a separate flag.
            ->whereIn('profession', $this->licensedProfessions())
            ->when($request->string('search')->trim()->value(), function (Builder $query, string $term): void {
                $query->where(function (Builder $builder) use ($term): void {
                    $builder->where('full_name', 'like', "%{$term}%")
                        ->orWhere('staff_code', 'like', "%{$term}%")
                        ->orWhere('professional_license', 'like', "%{$term}%")
                        ->orWhere('registration_number', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('speciality'), fn (Builder $q) => $q->where('speciality', $request->string('speciality')))
            ->when($request->filled('sub_speciality'), fn (Builder $q) => $q->where('sub_speciality', $request->string('sub_speciality')))
            ->when($request->filled('department'), fn (Builder $q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('license_status'), fn (Builder $q) => $q->where('license_status', $request->string('license_status')))
            ->when($request->filled('practice_status'), fn (Builder $q) => $q->where('practice_status', $request->string('practice_status')))
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('administration.physicians.index', [
            'physicians' => $physicians,
            'departments' => Department::query()->active()->orderBy('name')->get(),
            'filters' => $request->only([
                'search', 'speciality', 'sub_speciality', 'department', 'license_status', 'practice_status',
            ]),
        ]);
    }

    /** The licensing/practice form for one staff record. */
    public function edit(Staff $staff): View
    {
        $this->authorize('managePhysicianProfile', $staff);

        return view('administration.physicians.edit', [
            'staff' => $staff->load('qualifications'),
        ]);
    }

    public function update(PhysicianProfileRequest $request, Staff $staff): RedirectResponse
    {
        $this->authorize('managePhysicianProfile', $staff);

        DB::transaction(function () use ($request, $staff): void {
            $staff->fill($request->validated());

            $changed = array_keys($staff->getDirty());
            $staff->save();

            // Recalculate from the dates, which leaves a Suspended or Revoked
            // status exactly where the administrator just put it.
            $this->licences->apply($staff);

            $this->audit->record(
                AuditAction::StaffUpdated,
                $staff,
                "Physician profile updated for staff {$staff->staff_code}.",
                ['changed' => $changed, 'license_status' => $staff->license_status?->value],
                $request->user(),
            );
        });

        return redirect()
            ->route('administration.staff.show', $staff)
            ->with('success', "Physician profile updated for {$staff->full_name}.");
    }

    public function storeQualification(StaffQualificationRequest $request, Staff $staff): RedirectResponse
    {
        $this->authorize('manageQualifications', $staff);

        $qualification = new StaffQualification($request->validated());
        $qualification->staff_id = $staff->getKey();
        $qualification->save();

        $this->audit->record(
            AuditAction::StaffUpdated,
            $staff,
            "Qualification added for staff {$staff->staff_code}: {$qualification->summary()}.",
            ['qualification' => 'added'],
            $request->user(),
        );

        return back()->with('success', 'Qualification added.');
    }

    public function destroyQualification(Request $request, Staff $staff, StaffQualification $qualification): RedirectResponse
    {
        $this->authorize('manageQualifications', $staff);

        // Route model binding resolves the qualification independently, so it
        // is confirmed to belong to this staff record before removal.
        abort_unless($qualification->staff_id === $staff->getKey(), 404);

        $summary = $qualification->summary();
        $qualification->delete();

        $this->audit->record(
            AuditAction::StaffUpdated,
            $staff,
            "Qualification removed for staff {$staff->staff_code}: {$summary}.",
            ['qualification' => 'removed'],
            $request->user(),
        );

        return back()->with('success', 'Qualification removed.');
    }

    /** @return list<string> */
    private function licensedProfessions(): array
    {
        return array_values(array_map(
            static fn ($case): string => $case->value,
            array_filter(
                Profession::cases(),
                static fn ($case): bool => $case->requiresLicence(),
            ),
        ));
    }
}
