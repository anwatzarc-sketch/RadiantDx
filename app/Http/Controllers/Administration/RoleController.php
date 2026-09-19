<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Administration\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($request->string('search')->trim()->value(), function ($query, string $term): void {
                $query->where(function ($builder) use ($term): void {
                    $builder->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->orderByDesc('is_super_admin')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('administration.roles.index', [
            'roles' => $roles,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('administration.roles.create', [
            'permissionModules' => Permission::groupedByModule(),
            'assigned' => [],
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->roles->create($request->validated(), $request->permissionNames(), $request->user());

        return redirect()
            ->route('administration.roles.show', $role)
            ->with('success', "Role {$role->name} created.");
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);

        return view('administration.roles.show', [
            'role' => $role->loadCount('users'),
            'permissionModules' => Permission::groupedByModule(),
            'assigned' => $role->isSuperAdmin()
                ? Permission::query()->pluck('name')->all()
                : $role->permissions->pluck('name')->all(),
        ]);
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('administration.roles.edit', [
            'role' => $role,
            'permissionModules' => Permission::groupedByModule(),
            'assigned' => $role->isSuperAdmin()
                ? Permission::query()->pluck('name')->all()
                : $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        // Permission grants are only accepted when the user may assign them and
        // the role is not the protected administrative one.
        $permissions = $request->user()->can('assignPermissions', $role)
            ? $request->permissionNames()
            : null;

        $this->roles->update($role, $request->validated(), $permissions, $request->user());

        return redirect()
            ->route('administration.roles.show', $role)
            ->with('success', "Role {$role->name} updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $name = $role->name;
        $this->roles->delete($role, $request->user());

        return redirect()
            ->route('administration.roles.index')
            ->with('success', "Role {$name} deleted.");
    }
}
