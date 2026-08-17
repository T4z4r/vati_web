@extends('layouts.admin')
@section('title', __('Group Visits'))
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('FIELD OPERATIONS') }}</p>
            <h1>{{ __('Group Visits') }}</h1>
            <p>{{ __('Log and track field visits to member groups.') }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.group-visits.create') }}"><span class="ph ph-plus" aria-hidden="true"></span> {{ __('Record visit') }}</a>
    </div>
    <form class="filters">
        <select name="group_id">
            <option value="">{{ __('All groups') }}</option>
            @foreach($groups as $group)
                <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>{{ $group->group_name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}">
        <input type="date" name="to" value="{{ request('to') }}">
        <button class="btn btn-secondary">{{ __('Filter') }}</button>
    </form>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Group') }}</th>
                        <th>{{ __('Officer') }}</th>
                        <th>{{ __('Purpose') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th class="actions-col">{{ __('Actions') }}</th>
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
                                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.group-visits.show', $visit) }}">{{ __('View') }}</a>
                                    <form method="POST" action="{{ route('admin.group-visits.destroy', $visit) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" data-confirm="{{ __('Delete this visit record?') }}">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">{{ __('No group visits recorded yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $visits])
    </div>
@endsection
