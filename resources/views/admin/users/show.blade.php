@extends('layouts.admin')
@section('title', $user->name)
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('STAFF ACCOUNT') }}</p>
            <h1>{{ $user->name }}</h1>
            <p>{{ $user->email }}</p>
        </div>
        <div class="head-actions"><span
                class="badge {{ $user->status ? 'active' : 'inactive' }}">{{ $user->status ? __('Active') : __('Inactive') }}</span><a
                class="btn btn-primary" href="{{ route('admin.users.edit', $user) }}">{{ __('Edit') }}</a>
            @unless (auth()->id() === $user->id)
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}">@csrf @method('DELETE')<button
                        class="btn btn-danger" data-confirm="{{ __('Delete this staff account?') }}">{{ __('Delete') }}</button>
                </form>
            @endunless
        </div>
    </div>
    <div class="grid-2 grid-even">
        <div>
            <h2 class="section-title">{{ __('Assignment') }}</h2>
            <table class="detail-table">
                <tbody>
                    <tr>
                        <th>{{ __('Role') }}</th><td>{{ ucwords(str_replace('_', ' ', $user->roles->first()?->name ?? __('Unassigned'))) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Branch') }}</th><td>{{ $user->branch?->branch_name ?? __('Organization-wide') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head">
                <h2>{{ __('Effective permissions') }}</h2><span>{{ $user->getAllPermissions()->count() }}</span>
            </div>
            <div class="card-body">
                @foreach ($user->getAllPermissions() as $permission)
                    <span class="badge active" style="margin:3px">{{ str_replace('-', ' ', $permission->name) }}</span>
                @endforeach
            </div>
        </div>
    </div>
@endsection
