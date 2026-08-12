@extends('layouts.admin')
@section('title', $group->exists ? 'Edit Group' : 'Create Group')
@section('content')
@php($editing = $group->exists)
<div class="page-head">
    <div>
        <p class="eyebrow">GROUP LENDING</p>
        <h1>{{ $editing ? 'Edit member group' : 'Create a member group' }}</h1>
        <p>Assign an operating branch, meeting schedule, and loan officer.</p>
    </div>
    <a class="btn btn-secondary" href="{{ $editing ? route('admin.groups.show', $group) : route('admin.groups.index') }}">Back</a>
</div>

<form class="card" method="POST" action="{{ $editing ? route('admin.groups.update', $group) : route('admin.groups.store') }}">
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="card-body">
        <div class="form-grid">
            <label>Branch<select name="branch_id" required><option value="">Select branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $group->branch_id) === (string) $branch->id)>{{ $branch->branch_name }}</option>@endforeach</select></label>
            <label>Group name<input name="group_name" value="{{ old('group_name', $group->group_name) }}" required></label>
            <label>Loan officer<select name="loan_officer_id"><option value="">Unassigned</option>@foreach($officers as $officer)<option value="{{ $officer->id }}" @selected((string) old('loan_officer_id', $group->loan_officer_id) === (string) $officer->id)>{{ $officer->name }}</option>@endforeach</select></label>
            <label>Meeting day<select name="meeting_day"><option value="">Select day</option>@foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day)<option value="{{ $day }}" @selected(old('meeting_day', $group->meeting_day) === $day)>{{ $day }}</option>@endforeach</select></label>
            <label>Meeting time<input type="time" name="meeting_time" value="{{ old('meeting_time', $group->meeting_time ? substr($group->meeting_time, 0, 5) : null) }}"></label>
            <label>Region<input name="region" value="{{ old('region', $group->region) }}"></label>
            <label>District<input name="district" value="{{ old('district', $group->district) }}"></label>
            <label>Ward<input name="ward" value="{{ old('ward', $group->ward) }}"></label>
            <label>Meeting location<input name="location" value="{{ old('location', $group->location) }}"></label>
            @if($editing)
                <label>Status<select name="status"><option value="1" @selected(old('status', (int) $group->status) == 1)>Active</option><option value="0" @selected(old('status', (int) $group->status) == 0)>Inactive</option></select></label>
            @endif
        </div>
        <p class="muted">{{ $editing ? 'The group code cannot be changed.' : 'The group code is generated automatically when saved.' }}</p>
        <div class="form-actions">
            <a class="btn btn-secondary" href="{{ $editing ? route('admin.groups.show', $group) : route('admin.groups.index') }}">Cancel</a>
            <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create group' }}</button>
        </div>
    </div>
</form>
@endsection
