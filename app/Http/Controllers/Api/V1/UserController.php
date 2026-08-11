<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends ApiController
{
    public function index(Request $request)
    {
        return User::with('branch', 'roles')->when($request->branch_id, fn ($q, $v) => $q->where('branch_id', $v))->paginate($this->perPage($request));
    }

    public function store(Request $request)
    {
        $data = $this->data($request);
        $roles = Arr::pull($data, 'roles', []);
        $user = User::create($data);
        $user->syncRoles($roles);

        return response()->json(['success' => true, 'message' => 'User created successfully.', 'data' => $user->load('branch', 'roles')], 201);
    }

    public function show(User $user)
    {
        return response()->json(['success' => true, 'data' => $user->load('branch', 'roles', 'permissions')]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->data($request, $user);
        $roles = Arr::pull($data, 'roles');
        if (empty($data['password'])) {
            unset($data['password']);
        } $user->update($data);
        if ($roles !== null) {
            $user->syncRoles($roles);
        }

return response()->json(['success' => true, 'data' => $user->refresh()->load('branch', 'roles')]);
    }

    public function destroy(User $user)
    {
        abort_if($user->is(auth()->user()), 409, 'You cannot delete your own account.');
        $user->delete();

        return response()->noContent();
    }

    public function roles()
    {
        return response()->json(['success' => true, 'data' => Role::with('permissions')->get()]);
    }

    private function data(Request $request, ?User $user = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', Rule::unique('users')->ignore($user)], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:10'], 'branch_id' => ['nullable', 'exists:branches,id'], 'status' => ['sometimes', 'boolean'], 'roles' => ['sometimes', 'array'], 'roles.*' => ['string', 'exists:roles,name']]);
    }
}
