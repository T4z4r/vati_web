@extends('layouts.admin')
@section('title', 'Staff Accounts')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('ACCESS CONTROL') }}</p>
            <h1>{{ __('Staff accounts') }}</h1>
            <p>{{ __('Manage portal access, roles, and branch assignment.') }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.users.create') }}">+ {{ __('Add staff user') }}</a>
    </div>
    <form class="filters"><input class="search" name="search" value="{{ request('search') }}"
            placeholder="{{ __('Search name or email') }}"><button class="btn btn-secondary">{{ __('Search') }}</button>
    </form>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Branch') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="actions-col">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td><a class="table-link"
                                    href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a><br><small>{{ $user->email }}</small>
                            </td>
                            <td>{{ $user->branch?->branch_name ?? __('Organization-wide') }}</td>
                            <td>{{ str_replace('_', ' ', $user->roles->first()?->name ?? __('Unassigned')) }}</td>
                            <td><span
                                    class="badge {{ $user->status ? 'active' : 'inactive' }}">{{ $user->status ? __('Active') : __('Inactive') }}</span>
                            </td>
                            <td>{{ $user->created_at->format('d M Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-secondary"
                                        href="{{ route('admin.users.show', $user) }}">{{ __('View') }}</a>
                                    <a class="btn btn-sm btn-primary"
                                        href="{{ route('admin.users.edit', $user) }}">{{ __('Edit') }}</a>
                                    @unless (auth()->id() === $user->id)
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger"
                                                data-confirm="{{ __('Delete this staff account?') }}">{{ __('Delete') }}</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">{{ __('No staff accounts found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $users])
    </div>

    <br>
    <div class="card">
        <div class="card-head">
            <div>
                <h2>{{ __('Role permission assignment') }}</h2>
                <small>{{ __('Permissions apply immediately to every staff account assigned to the role.') }}</small>
            </div>
            <span class="badge active">{{ $permissions->count() }} {{ __('permissions') }}</span>
        </div>
        <div class="card-body">
            @foreach($roles as $role)
                @php
                    $assignedPermissions = $role->permissions->pluck('name')->all();
                    $locked = $role->name === 'super_admin' || ! auth()->user()->hasRole('super_admin');
                @endphp
                <details class="detail" style="margin-bottom:12px" @if($errors->has('permissions.*')) open @endif>
                    <summary style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:15px">
                        <strong>{{ ucwords(str_replace('_', ' ', $role->name)) }}</strong>
                        <span class="badge {{ count($assignedPermissions) ? 'active' : 'pending' }}">{{ count($assignedPermissions) }} / {{ $permissions->count() }}</span>
                    </summary>
                    <div style="margin-top:18px">
                        @if($role->name === 'super_admin')
                            <div class="alert alert-success">{{ __('Locked with every system permission to preserve administrative access.') }}</div>
                        @elseif(! auth()->user()->hasRole('super_admin'))
                            <div class="alert">{{ __('Only a super administrator can change role permissions.') }}</div>
                        @endif
                        <form method="POST" action="{{ route('admin.roles.permissions.update', $role) }}">
                            @csrf @method('PUT')
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:9px">
                                @foreach($permissions as $permission)
                                    <label class="check" style="margin:0;padding:9px 11px;border:1px solid var(--line);border-radius:8px">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                            @checked(in_array($permission->name, $assignedPermissions, true)) @disabled($locked)>
                                        <span>{{ ucwords(str_replace(['-', '_'], ' ', $permission->name)) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @unless($locked)
                                <div class="form-actions">
                                    <button class="btn btn-primary" data-confirm="Update permissions for this role? Every assigned user will be affected immediately.">{{ __('Save role permissions') }}</button>
                                </div>
                            @endunless
                        </form>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
@endsection
