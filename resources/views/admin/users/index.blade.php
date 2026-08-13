@extends('layouts.admin')
@section('title', 'Staff Accounts')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('ACCESS CONTROL') }}</p>
            <h1>{{ __('Staff accounts') }}</h1>
            <p>{{ __('Manage portal access, roles, and branch assignment.') }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-secondary" href="{{ route('admin.roles.permissions.index') }}">{{ __('Roles & Permissions') }}</a>
            <a class="btn btn-primary" href="{{ route('admin.users.create') }}"><span class="ph ph-user-plus" aria-hidden="true"></span> {{ __('Add staff user') }}</a>
        </div>
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

@endsection
