<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $search = $request->string('search')->trim()->toString();

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderByRaw("name = 'super_admin' desc")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'updated_at' => optional($role->updated_at)?->toDateTimeString(),
                'is_super_admin' => $role->name === 'super_admin',
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => ['search' => $search],
            'can' => [
                'create' => $request->user()->can('create', Role::class),
                'deleteAny' => $request->user()->can('deleteAny', Role::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Roles/Form', [
            'role' => null,
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $this->validated($request);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()
            ->route('app.roles.index')
            ->with('success', 'Role created.');
    }

    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        $role->load('permissions');

        return Inertia::render('Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name')->values(),
                'is_super_admin' => $role->name === 'super_admin',
            ],
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $this->validated($request, $role);

        if ($role->name !== 'super_admin') {
            $role->update([
                'name' => $data['name'],
                'guard_name' => $data['guard_name'] ?? $role->guard_name,
            ]);
        }

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()
            ->route('app.roles.index')
            ->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        abort_if($role->name === 'super_admin', 403, 'Cannot delete the super_admin role.');

        $role->delete();

        return redirect()
            ->route('app.roles.index')
            ->with('success', 'Role deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', Role::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:roles,id'],
        ]);

        Role::query()
            ->whereIn('id', $data['ids'])
            ->where('name', '!=', 'super_admin')
            ->delete();

        return back()->with('success', 'Roles deleted.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string, permissions: array<int, array{name: string, label: string}>}>
     */
    private function permissionGroups(): array
    {
        return PermissionCatalog::groups();
    }
}
