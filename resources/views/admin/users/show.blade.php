@extends('layouts.admin')
@section('title',$user->name)
@section('content')
<div class="page-head"><div><p class="eyebrow">STAFF ACCOUNT</p><h1>{{ $user->name }}</h1><p>{{ $user->email }}</p></div><span class="badge {{ $user->status?'active':'inactive' }}">{{ $user->status?'Active':'Inactive' }}</span></div>
<div class="grid-2 grid-even"><div class="card"><div class="card-head"><h2>Assignment</h2></div><div class="card-body detail-grid" style="grid-template-columns:1fr 1fr"><div class="detail"><small>Role</small><strong>{{ ucwords(str_replace('_',' ',$user->roles->first()?->name??'Unassigned')) }}</strong></div><div class="detail"><small>Branch</small><strong>{{ $user->branch?->branch_name??'Organization-wide' }}</strong></div></div></div><div class="card"><div class="card-head"><h2>Effective permissions</h2><span>{{ $user->getAllPermissions()->count() }}</span></div><div class="card-body">@foreach($user->getAllPermissions() as $permission)<span class="badge active" style="margin:3px">{{ str_replace('-',' ',$permission->name) }}</span>@endforeach</div></div></div>
@endsection
