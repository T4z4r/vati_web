@extends('layouts.admin')
@section('title', 'Roles & Permissions')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('ACCESS CONTROL') }}</p>
            <h1>{{ __('Roles & Permissions') }}</h1>
            <p>{{ __('Assign system permissions to staff roles from this dedicated page.') }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">{{ __('Staff Accounts') }}</a>
    </div>

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
