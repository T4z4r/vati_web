<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['branch', 'roles'])->when($request->search, fn ($q, $value) => $q->where(fn ($q) => $q->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%")))->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.create', $this->formData(new User));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'max:150'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'min:10'], 'branch_id' => ['nullable', 'exists:branches,id'], 'role' => ['required', 'exists:roles,name']]);
        $role = $data['role'];
        unset($data['role']);
        $user = User::create($data);
        $user->assignRole($role);

        return redirect()->route('admin.users.show', $user)->with('success', 'Staff account created.');
    }

    public function show(User $user)
    {
        return view('admin.users.show', ['user' => $user->load('branch', 'roles', 'permissions')]);
    }

    public function edit(User $user)
    {
        return view('admin.users.create', $this->formData($user->load('roles')));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'min:10'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role' => ['required', 'exists:roles,name'],
            'status' => ['nullable', 'boolean'],
        ]);

        $role = $data['role'];
        unset($data['role']);

        $data['status'] = $request->boolean('status');
        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        $user->syncRoles($role);

        return redirect()->route('admin.users.show', $user)->with('success', 'Staff account updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot delete your own signed-in account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Staff account deleted.');
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        if ($role->guard_name !== 'web') {
            abort(404);
        }
        if ($role->name === 'super_admin') {
            return back()->with('error', 'The super administrator permissions are locked to preserve system access.');
        }

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);
        $permissions = collect($data['permissions'] ?? [])->unique()->values()->all();
        $role->syncPermissions($permissions);

        activity()
            ->causedBy($request->user())
            ->withProperties(['role' => $role->name, 'permissions' => $permissions])
            ->log('Role permissions updated');

        return back()->with('success', 'Permissions updated for '.str_replace('_', ' ', $role->name).'.');
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'branches' => Branch::where('status', true)->orderBy('branch_name')->get(),
            'roles' => Role::orderBy('name')->get(),
        ];
    }
}
