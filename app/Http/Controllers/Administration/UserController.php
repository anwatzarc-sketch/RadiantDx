<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\UserRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\Administration\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('role')
            ->when($request->string('search')->trim()->value(), function ($query, string $term): void {
                $query->where(function ($builder) use ($term): void {
                    $builder->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role_id', $request->integer('role')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('administration.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('administration.users.create', [
            'roles' => Role::query()->active()->orderBy('name')->get(),
            'staff' => $this->staffAwaitingAccounts(),
        ]);
    }

    /**
     * Staff who may still be given an account.
     *
     * Only Active standing permits system access, and `users.staff_id` carries
     * a plain unique index, so one staff record supports one account for good.
     * The existence check therefore has to see soft-deleted accounts too: such
     * a row still occupies the staff_id, and offering it here would produce a
     * duplicate key error on submit rather than a validation message.
     *
     * @return EloquentCollection<int, Staff>
     */
    private function staffAwaitingAccounts(): EloquentCollection
    {
        return Staff::query()
            ->active()
            ->whereDoesntHave('user', fn (Builder $query) => $query->withTrashed())
            ->orderBy('full_name')
            ->get(['id', 'staff_code', 'full_name', 'position']);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = $this->users->create($request->validatedAttributes(), $request->user());

        return redirect()
            ->route('administration.users.show', $user)
            ->with('success', "User {$user->name} created. They will be asked to choose a new password at first sign in.");
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('administration.users.show', [
            'user' => $user->load('role.permissions'),
            'activity' => AuditLog::query()
                ->where('user_id', $user->getKey())
                ->latest('created_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        return view('administration.users.edit', [
            'user' => $user,
            'roles' => Role::query()->active()->orderBy('name')->get(),
            'canChangeRole' => $request->user()->can('changeRole', $user),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $attributes = $request->validatedAttributes();

        // The role field is only accepted when changing it is actually allowed.
        if (! $request->user()->can('changeRole', $user)) {
            unset($attributes['role_id']);
        }

        $this->users->update($user, $attributes, $request->user());

        return redirect()
            ->route('administration.users.show', $user)
            ->with('success', "User {$user->name} updated.");
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('activate', $user);

        $this->users->setActive($user, true, $request->user());

        return back()->with('success', "User {$user->name} activated.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $this->users->setActive($user, false, $request->user());

        return back()->with('success', "User {$user->name} deactivated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $name = $user->name;
        $this->users->delete($user, $request->user());

        return redirect()
            ->route('administration.users.index')
            ->with('success', "User {$name} deleted.");
    }
}
