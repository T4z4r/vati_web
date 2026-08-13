@extends('layouts.admin')
@section('title', 'Organization')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('ORGANIZATION STRUCTURE') }}</p>
            <h1>{{ __('Regions, areas & branches') }}</h1>
            <p>{{ __('Maintain the operating hierarchy used for access and reporting.') }}</p>
        </div>
    </div>
    <div class="grid-2 grid-even">
        <div class="card">
            <div class="card-head">
                <h2>{{ __('Add region') }}</h2>
            </div>
            <form class="card-body" method="POST" action="{{ route('admin.regions.store') }}">@csrf<div class="form-grid">
                    <label>{{ __('Name') }}<input name="name" required></label></div>
                <p class="muted">{{ __('The region code is generated automatically when saved.') }}</p>
                <div class="form-actions"><button class="btn btn-primary">{{ __('Save region') }}</button></div>
            </form>
        </div>
        <div class="card">
            <div class="card-head">
                <h2>{{ __('Add area') }}</h2>
            </div>
            <form class="card-body" method="POST" action="{{ route('admin.areas.store') }}">@csrf<div class="form-grid">
                    <label>{{ __('Region') }}<select name="region_id" required>
                            <option value="">{{ __('Select') }}</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}">{{ $region->name }}</option>
                            @endforeach
                        </select>
                    </label><label>{{ __('Name') }}<input name="name" required></label></div>
                <p class="muted">{{ __('The area code is generated automatically when saved.') }}</p>
                <div class="form-actions"><button class="btn btn-primary">{{ __('Save area') }}</button></div>
            </form>
        </div>
    </div><br>
    <div class="card">
        <div class="card-head">
            <h2>{{ __('Add branch') }}</h2>
        </div>
        <form class="card-body" method="POST" action="{{ route('admin.branches.store') }}">@csrf<div class="form-grid">
                <label>{{ __('Area') }}<select name="area_id" required>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->region->name }} · {{ $area->name }}</option>
                        @endforeach
                    </select>
                </label><label>{{ __('Branch name') }}<input name="branch_name"
                        required></label><label>{{ __('Phone') }}<input
                        name="phone"></label><label>{{ __('Email') }}<input type="email"
                        name="email"></label><label>{{ __('Address') }}<input name="address"></label></div>
            <p class="muted">{{ __('The branch code is generated automatically when saved.') }}</p>
            <div class="form-actions"><button class="btn btn-primary">{{ __('Create branch') }}</button></div>
        </form>
    </div><br>
    <div class="card">
        <div class="card-head">
            <h2>{{ __('Branch directory') }}</h2><span class="muted">{{ $branches->count() }}
                {{ __('branches') }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Branch') }}</th>
                        <th>{{ __('Area / Region') }}</th>
                        <th>{{ __('Members') }}</th>
                        <th>{{ __('Groups') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($branches as $branch)
                        <tr>
                            <td><strong>{{ $branch->branch_name }}</strong><br><small
                                    class="muted">{{ $branch->branch_code }}</small></td>
                            <td>{{ $branch->area?->name }} / {{ $branch->area?->region?->name }}</td>
                            <td>{{ number_format($branch->members_count) }}</td>
                            <td>{{ number_format($branch->groups_count) }}</td>
                            <td><span
                                    class="badge {{ $branch->status ? 'active' : 'inactive' }}">{{ $branch->status ? __('Active') : __('Inactive') }}</span>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="5" class="empty">{{ __('Create your first branch above.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
