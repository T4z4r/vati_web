@extends('layouts.admin')
@section('title','Staff Accounts')
@section('content')
<div class="page-head">
    <div><p class="eyebrow">ACCESS CONTROL</p><h1>Staff accounts</h1><p>Manage portal access, roles, and branch assignment.</p></div>
    <a class="btn btn-primary" href="{{ route('admin.users.create') }}">+ Add staff user</a>
</div>
<form class="filters"><input class="search" name="search" value="{{ request('search') }}" placeholder="Search name or email"><button class="btn btn-secondary">Search</button></form>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Branch</th><th>Role</th><th>Status</th><th>Created</th><th class="actions-col">Actions</th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td><a class="table-link" href="{{ route('admin.users.show',$user) }}">{{ $user->name }}</a><br><small>{{ $user->email }}</small></td>
                    <td>{{ $user->branch?->branch_name ?? 'Organization-wide' }}</td>
                    <td>{{ str_replace('_',' ',$user->roles->first()?->name ?? 'Unassigned') }}</td>
                    <td><span class="badge {{ $user->status ? 'active' : 'inactive' }}">{{ $user->status ? 'Active' : 'Inactive' }}</span></td>
                    <td>{{ $user->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-sm btn-secondary" href="{{ route('admin.users.show',$user) }}">View</a>
                            <a class="btn btn-sm btn-primary" href="{{ route('admin.users.edit',$user) }}">Edit</a>
                            @unless(auth()->id() === $user->id)
                                <form method="POST" action="{{ route('admin.users.destroy',$user) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" data-confirm="Delete this staff account?">Delete</button>
                                </form>
                            @endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No staff accounts found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pagination',['paginator'=>$users])
</div>
@endsection
