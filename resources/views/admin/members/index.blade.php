@extends('layouts.admin')
@section('title','Members')
@section('content')
<div class="page-head">
    <div><p class="eyebrow">MEMBER MANAGEMENT</p><h1>Members</h1><p>Search and manage registered VATI members.</p></div>
    <a class="btn btn-primary" href="{{ route('admin.members.create') }}">+ Register member</a>
</div>
<form class="filters"><input class="search" name="search" value="{{ request('search') }}" placeholder="Search name, number or phone"><select name="group_id"><option value="">All groups</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(request('group_id')==$group->id)>{{ $group->group_name }}</option>@endforeach</select><select name="status"><option value="">All statuses</option>@foreach(['active','inactive','suspended','closed'] as $s)<option @selected(request('status')==$s)>{{ $s }}</option>@endforeach</select><button class="btn btn-secondary">Filter</button></form>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Member</th><th>Contact</th><th>Group</th><th>Branch</th><th>Joined</th><th>Status</th><th class="actions-col">Actions</th></tr></thead>
            <tbody>
            @forelse($members as $member)
                <tr>
                    <td><a class="table-link" href="{{ route('admin.members.show',$member) }}">{{ $member->first_name }} {{ $member->middle_name }} {{ $member->last_name }}</a><br><small class="muted">{{ $member->membership_number }}</small></td>
                    <td>{{ $member->phone }}</td>
                    <td>{{ $member->group->group_name }}</td>
                    <td>{{ $member->branch->branch_name }}</td>
                    <td>{{ $member->admission_date?->format('d M Y') ?? $member->created_at->format('d M Y') }}</td>
                    <td><span class="badge {{ $member->status }}">{{ $member->status }}</span></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-sm btn-secondary" href="{{ route('admin.members.show',$member) }}">View</a>
                            @can('edit-members')<a class="btn btn-sm btn-primary" href="{{ route('admin.members.edit',$member) }}">Edit</a>@endcan
                            @can('delete-members')
                                <form method="POST" action="{{ route('admin.members.destroy',$member) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" data-confirm="Delete this member? Members with loan history cannot be deleted.">Delete</button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No members match your filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pagination',['paginator'=>$members])
</div>
@endsection
