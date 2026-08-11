<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['branch', 'roles'])->when($request->search, fn ($q, $value) => $q->where(fn ($q) => $q->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%")))->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create', ['branches' => Branch::where('status', true)->orderBy('branch_name')->get(), 'roles' => Role::orderBy('name')->get()]);
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
}
