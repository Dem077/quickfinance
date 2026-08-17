<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Departments;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $authUser = $request->user();
        $search = $request->string('search')->trim()->toString();
        $departmentId = $request->integer('department_id') ?: null;
        $role = $request->string('role')->trim()->toString();
        $signature = $request->string('signature')->trim()->toString();
        $sort = $request->string('sort')->trim()->toString() ?: 'newest';

        if (! in_array($signature, ['has', 'missing'], true)) {
            $signature = '';
        }

        $sortMap = [
            'newest' => ['id', 'desc'],
            'oldest' => ['id', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
        ];

        if (! array_key_exists($sort, $sortMap)) {
            $sort = 'newest';
        }

        [$sortColumn, $sortDirection] = $sortMap[$sort];

        $users = User::query()
            ->with(['roles:id,name', 'department:id,name', 'location:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($role !== '', fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $role)))
            ->when($signature === 'has', fn ($query) => $query->whereNotNull('signature')->where('signature', '!=', ''))
            ->when($signature === 'missing', function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('signature')->orWhere('signature', '');
                });
            })
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'designation' => $user->designation,
                'department' => $user->department?->name,
                'location' => $user->location?->name,
                'avatar_url' => $user->getFilamentAvatarUrl(),
                'has_signature' => filled($user->signature),
                'roles' => $user->roles->pluck('name')->all(),
                'actions' => [
                    'edit' => $authUser->can('update', $user),
                    'delete' => $authUser->can('delete', $user),
                ],
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'role' => $role,
                'signature' => $signature,
                'sort' => $sort,
            ],
            'filterOptions' => [
                'departments' => Departments::query()->orderBy('name')->get(['id', 'name']),
                'roles' => Role::query()->orderBy('name')->pluck('name'),
                'sorts' => [
                    ['value' => 'newest', 'label' => 'Newest first'],
                    ['value' => 'oldest', 'label' => 'Oldest first'],
                    ['value' => 'name_asc', 'label' => 'Name (A–Z)'],
                    ['value' => 'name_desc', 'label' => 'Name (Z–A)'],
                ],
            ],
            'can' => [
                'create' => $authUser->can('create', User::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Form', [
            'user' => null,
            ...$this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $this->validated($request, creating: true);

        $roles = $data['roles'];
        unset($data['roles'], $data['password_confirmation'], $data['pettycashassigned']);
        $data['signature'] = filled($data['signature'] ?? null) ? $data['signature'] : null;

        $user = User::create($data);
        $user->syncRoles($roles);

        return redirect()
            ->route('app.users.edit', $user)
            ->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['roles:id,name', 'hodof:id,name,hod']);

        return Inertia::render('Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'designation' => $user->designation,
                'department_id' => $user->department_id,
                'location_id' => $user->location_id,
                'view_all_pr' => (bool) $user->view_all_pr,
                'bank_account_name' => $user->bank_account_name,
                'bank_account_no' => $user->bank_account_no,
                'roles' => $user->roles->pluck('name')->all(),
                'signature' => $user->signature,
                'has_signature' => filled($user->signature),
                'avatar_url' => $user->getFilamentAvatarUrl(),
                'hod_of' => $user->hodof->map(fn (Departments $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])->values()->all(),
            ],
            ...$this->options(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $this->validated($request, creating: false, user: $user);

        $roles = $data['roles'];
        unset($data['roles'], $data['password_confirmation'], $data['pettycashassigned']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if (array_key_exists('signature', $data)) {
            $data['signature'] = filled($data['signature']) ? $data['signature'] : null;
        }

        $user->update($data);
        $user->syncRoles($roles);

        return redirect()
            ->route('app.users.index')
            ->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        $user->delete();

        return redirect()
            ->route('app.users.index')
            ->with('success', 'User deleted.');
    }

    public function associateHod(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
        ]);

        Departments::query()->whereKey($data['department_id'])->update(['hod' => $user->id]);

        return back()->with('success', 'Department associated as HOD.');
    }

    public function dissociateHod(User $user, Departments $department): RedirectResponse
    {
        $this->authorize('update', $user);

        if ((int) $department->hod !== (int) $user->id) {
            return back()->with('error', 'Department is not associated to this user.');
        }

        $department->update(['hod' => null]);

        return back()->with('success', 'Department dissociated.');
    }

    public function dissociateHodMany(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:departments,id'],
        ]);

        Departments::query()
            ->whereIn('id', $data['ids'])
            ->where('hod', $user->id)
            ->update(['hod' => null]);

        return back()->with('success', 'Departments dissociated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, bool $creating, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'designation' => ['required', 'string', 'min:2', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'mobile' => ['required', 'string', 'max:255'],
            'view_all_pr' => ['required', 'boolean'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:255'],
            'pettycashassigned' => ['nullable', 'in:Yes,No'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'password' => [
                $creating ? 'required' : 'nullable',
                'confirmed',
                'min:8',
            ],
            'signature' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function options(): array
    {
        return [
            'departments' => Departments::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'availableHodDepartments' => Departments::query()
                ->whereNull('hod')
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
