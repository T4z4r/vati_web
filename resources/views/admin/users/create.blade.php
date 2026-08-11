@extends('layouts.admin')
@section('title','Add Staff User')
@section('content')
<div class="page-head"><div><p class="eyebrow">ACCESS CONTROL</p><h1>Create staff account</h1><p>Assign the least-privileged role and correct operating branch.</p></div><a class="btn btn-secondary" href="{{ route('admin.users.index') }}">← Back</a></div>
<form class="card" method="POST" action="{{ route('admin.users.store') }}">@csrf<div class="card-body form-grid"><label>Full name<input name="name" required></label><label>Email address<input type="email" name="email" required></label><label>Temporary password<input type="password" name="password" minlength="10" required></label><label>Branch<select name="branch_id"><option value="">Organization-wide</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->branch_name }}</option>@endforeach</select></label><label>Role<select name="role" required>@foreach($roles as $role)<option value="{{ $role->name }}">{{ ucwords(str_replace('_',' ',$role->name)) }}</option>@endforeach</select></label><div class="full form-actions"><button class="btn btn-primary">Create account</button></div></div></form>
@endsection
