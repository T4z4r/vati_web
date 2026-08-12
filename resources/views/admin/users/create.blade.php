@extends('layouts.admin')
@section('title', $user->exists ? 'Edit Staff User' : 'Add Staff User')
@section('content')
@php($editing = $user->exists)
<div class="page-head">
    <div>
        <p class="eyebrow">ACCESS CONTROL</p>
        <h1>{{ $editing ? 'Edit staff account' : 'Create staff account' }}</h1>
        <p>Assign the least-privileged role and correct operating branch.</p>
    </div>
    <a class="btn btn-secondary" href="{{ $editing ? route('admin.users.show', $user) : route('admin.users.index') }}">Back</a>
</div>

<form class="card" method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="card-body form-grid">
        <label>Full name<input name="name" value="{{ old('name', $user->name) }}" required></label>
        <label>Email address<input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
        <label>{{ $editing ? 'New password' : 'Temporary password' }}<input type="password" name="password" minlength="10" @required(! $editing)></label>
        <label>Branch<select name="branch_id"><option value="">Organization-wide</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $user->branch_id) === (string) $branch->id)>{{ $branch->branch_name }}</option>@endforeach</select></label>
        <label>Role<select name="role" required>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(old('role', $user->roles->first()?->name) === $role->name)>{{ ucwords(str_replace('_', ' ', $role->name)) }}</option>@endforeach</select></label>
        @if($editing)
            <label>Status<select name="status"><option value="1" @selected(old('status', (int) $user->status) == 1)>Active</option><option value="0" @selected(old('status', (int) $user->status) == 0)>Inactive</option></select></label>
        @endif
        <div class="full form-actions">
            <a class="btn btn-secondary" href="{{ $editing ? route('admin.users.show', $user) : route('admin.users.index') }}">Cancel</a>
            <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create account' }}</button>
        </div>
    </div>
</form>
@endsection
