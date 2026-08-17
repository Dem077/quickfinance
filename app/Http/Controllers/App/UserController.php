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

        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->with(['roles:id,name', 'department:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department?->name,
                'roles' => $user->roles->pluck('name')->all(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'can' => [
                'create' => $request->user()->can('create', User::class),
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
