@extends('layouts.admin')
@section('title', 'Group Visits')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">FIELD OPERATIONS</p>
            <h1>Group Visits</h1>
            <p>Log and track field visits to member groups.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.group-visits.create') }}"><span class="ph ph-plus" aria-hidden="true"></span> Record visit</a>
    </div>
    <form class="filters">
        <select name="group_id">
            <option value="">All groups</option>
            @foreach($groups as $group)
                <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>{{ $group->group_name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" placeholder="From">
        <input type="date" name="to" value="{{ request('to') }}" placeholder="To">
        <button class="btn btn-secondary">Filter</button>
    </form>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Group</th>
                        <th>Officer</th>
                        <th>Purpose</th>
                        <th>Location</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visits as $visit)
                        <tr>
                            <td>{{ $visit->visit_date->format('d M Y') }}</td>
                            <td><a class="table-link" href="{{ route('admin.groups.show', $visit->group) }}">{{ $visit->group->group_name }}</a></td>
                            <td>{{ $visit->user->name }}</td>
                            <td>{{ $visit->purpose ?: '-' }}</td>
                            <td>{{ $visit->location ?: '-' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.group-visits.show', $visit) }}">View</a>
                                    <form method="POST" action="{{ route('admin.group-visits.destroy', $visit) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" data-confirm="Delete this visit record?">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">No group visits recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $visits])
    </div>
@endsection
