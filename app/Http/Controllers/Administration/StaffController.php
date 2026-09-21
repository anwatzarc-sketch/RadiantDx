<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\StaffRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\Staff;
use App\Rules\SharedEnumValue;
use App\Services\Administration\StaffService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private readonly StaffService $staff) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        return view('administration.staff.index', [
            'staff' => $this->filtered($request)->paginate(20)->withQueryString(),
            'departments' => Department::query()->active()->orderBy('name')->get(),
            'filters' => $request->only([
                'search', 'profession', 'speciality', 'department', 'status', 'account', 'review',
            ]),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Staff::class);

        return view('administration.staff.create', $this->formData());
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $this->authorize('create', Staff::class);

        $staff = $this->staff->create($request->validated(), $request->user());

        return redirect()
            ->route('administration.staff.show', $staff)
            ->with('success', "Staff {$staff->staff_code} created.");
    }

    public function show(Request $request, Staff $staff): View
    {
        $this->authorize('view', $staff);

        $staff->load(['user.role', 'department', 'unit', 'supervisor']);

        return view('administration.staff.show', [
            'staff' => $staff,
            'roles' => Role::query()->active()->orderBy('name')->get(),
            'activity' => $request->user()->can('viewActivity', $staff)
                ? $this->activityFor($staff)
                : collect(),
        ]);
    }

    public function edit(Staff $staff): View
    {
        $this->authorize('update', $staff);

        return view('administration.staff.edit', [
            'staff' => $staff->load(['department', 'unit']),
            ...$this->formData(),
        ]);
    }

    public function update(StaffRequest $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $this->staff->update($staff, $request->validated(), $request->user());

        return redirect()
            ->route('administration.staff.show', $staff)
            ->with('success', "Staff {$staff->staff_code} updated.");
    }

    /**
     * Changes standing.
     *
     * Separate from update() because it is separately permissioned and has
     * consequences beyond the record — retiring somebody also closes their
     * account.
     */
    public function changeStatus(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('changeStatus', $staff);

        $validated = $request->validate([
            'status' => ['required', new SharedEnumValue('StaffStatus')],
        ]);

        $this->staff->setStatus(
            $staff,
            StaffStatus::from($validated['status']),
            $request->user(),
        );

        return back()->with('success', "Staff {$staff->staff_code} is now {$staff->status->label()}.");
    }

    public function destroy(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        $staffNumber = $staff->staff_code;
        $this->staff->delete($staff, $request->user());

        return redirect()
            ->route('administration.staff.index')
            ->with('success', "Staff {$staffNumber} deleted.");
    }

    /**
     * The staff list query, shared by the index and the export so the two
     * cannot disagree about what "the current filtered set" means.
     *
     * @return Builder<Staff>
     */
    public function filtered(Request $request): Builder
    {
        return Staff::query()
            // Eager loaded: the list renders department, unit and account for
            // every row, which is three queries per row without this.
            ->with(['user:id,staff_id,email,is_active', 'department:id,name', 'unit:id,name'])
            ->when($request->string('search')->trim()->value(), function (Builder $query, string $term): void {
                $query->where(function (Builder $builder) use ($term): void {
                    $builder
                        ->where('full_name', 'like', "%{$term}%")
                        ->orWhere('staff_code', 'like', "%{$term}%")
                        ->orWhere('employee_id', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('position', 'like', "%{$term}%")
                        ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('profession'), fn (Builder $q) => $q->where('profession', $request->string('profession')))
            ->when($request->filled('speciality'), fn (Builder $q) => $q->where('speciality', $request->string('speciality')))
            ->when($request->filled('department'), fn (Builder $q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('review'), fn (Builder $q) => $q->where('needs_review', true))
            ->when($request->filled('account'), function (Builder $query) use ($request): void {
                match ($request->string('account')->value()) {
                    'none' => $query->whereDoesntHave('user'),
                    'active' => $query->whereHas('user', fn (Builder $u) => $u->where('is_active', true)),
                    'disabled' => $query->whereHas('user', fn (Builder $u) => $u->where('is_active', false)),
                    default => null,
                };
            })
            ->orderBy('full_name');
    }

    /** Options shared by the create and edit forms. */
    private function formData(): array
    {
        return [
            'departments' => Department::query()->active()->orderBy('name')->get(),
            'supervisors' => Staff::query()->active()->orderBy('full_name')->get(['id', 'staff_code', 'full_name']),
        ];
    }

    /**
     * This person's history, both administrative and laboratory.
     *
     * One feed rather than two: "what has this member of staff done" is a
     * single question, and splitting it by subsystem would make it answerable
     * only by reading two screens.
     */
    private function activityFor(Staff $staff): Collection
    {
        return AuditLog::query()
            ->where(function (Builder $query) use ($staff): void {
                // Acted as this person...
                $query->where('actor_staff_id', $staff->getKey())
                    // ...or the action was performed against their record.
                    ->orWhere(function (Builder $about) use ($staff): void {
                        $about->where('entity_type', Staff::class)
                            ->where('entity_id', $staff->getKey());
                    });

                if ($staff->user !== null) {
                    // Entries written before the staff link existed still carry
                    // only the user id.
                    $query->orWhere('user_id', $staff->user->getKey());
                }
            })
            ->latest('created_at')
            ->limit(50)
            ->get();
    }
}
