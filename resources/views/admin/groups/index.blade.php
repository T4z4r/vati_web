@extends('layouts.admin')
@section('title','Member Groups')
@section('content')
<div class="page-head">
    <div><p class="eyebrow">GROUP LENDING</p><h1>Member groups</h1><p>Manage lending groups, officers, membership, and portfolio context.</p></div>
    <a class="btn btn-primary" href="{{ route('admin.groups.create') }}">+ Create group</a>
</div>
<form class="filters"><input class="search" name="search" value="{{ request('search') }}" placeholder="Search name or group code"><button class="btn btn-secondary">Search</button></form>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Group</th><th>Branch</th><th>Meeting</th><th>Loan officer</th><th>Members</th><th>Loans</th><th>Status</th><th class="actions-col">Actions</th></tr></thead>
            <tbody>
            @forelse($groups as $group)
                <tr>
                    <td><a class="table-link" href="{{ route('admin.groups.show',$group) }}">{{ $group->group_name }}</a><br><small class="muted">{{ $group->group_code }}</small></td>
                    <td>{{ $group->branch->branch_name }}</td>
                    <td>{{ $group->meeting_day ?: '-' }} {{ $group->meeting_time ? ' - '.substr($group->meeting_time,0,5) : '' }}</td>
                    <td>{{ $group->loanOfficer?->name ?? 'Unassigned' }}</td>
                    <td>{{ $group->members_count }}</td>
                    <td>{{ $group->loans_count }}</td>
                    <td><span class="badge {{ $group->status ? 'active' : 'inactive' }}">{{ $group->status ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-sm btn-secondary" href="{{ route('admin.groups.show',$group) }}">View</a>
                            @can('edit-groups')<a class="btn btn-sm btn-primary" href="{{ route('admin.groups.edit',$group) }}">Edit</a>@endcan
                            @can('edit-groups')
                                <form method="POST" action="{{ route('admin.groups.destroy',$group) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" data-confirm="Delete this group? Groups with members or lending history cannot be deleted.">Delete</button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No groups found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pagination',['paginator'=>$groups])
</div>
@endsection
