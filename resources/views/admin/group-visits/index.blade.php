@extends('layouts.admin')
@section('title', __('Group Visits'))
@section('content')
    <div class="page-head card">
        <div>
            <p class="eyebrow">{{ __('FIELD OPERATIONS') }}</p>
            <h1>{{ __('Group Visits') }}</h1>
            <p>{{ __('Log and track field visits to member groups.') }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-primary" href="{{ route('admin.group-visits.create') }}"><span class="ph ph-plus" aria-hidden="true"></span> {{ __('Record visit') }}</a>
            <a class="btn btn-secondary" href="{{ route('admin.group-visits.export.list', ['format' => 'pdf'] + request()->query()) }}" title="{{ __('Export PDF') }}"><span class="ph ph-file-pdf" aria-hidden="true"></span> {{ __('PDF') }}</a>
            <a class="btn btn-secondary" href="{{ route('admin.group-visits.export.list', ['format' => 'xlsx'] + request()->query()) }}" title="{{ __('Export Excel') }}"><span class="ph ph-file-xls" aria-hidden="true"></span> {{ __('Excel') }}</a>
        </div>
    </div>
    <div class="stats">
        <div class="stat">
            <span class="ph ph-map-pin stat-icon" aria-hidden="true"></span>
            <small>{{ __('Visits') }}</small><strong>{{ number_format($stats['total']) }}</strong><em>{{ __('Matching the current filters') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-calendar-blank stat-icon" aria-hidden="true"></span>
            <small>{{ __('Visits this month') }}</small><strong>{{ number_format($stats['thisMonth']) }}</strong><em>{{ __('Since the 1st') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-clock stat-icon" aria-hidden="true"></span>
            <small>{{ __('Visits today') }}</small><strong>{{ number_format($stats['today']) }}</strong><em>{{ now()->format('d M Y') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-users-four stat-icon" aria-hidden="true"></span>
            <small>{{ __('Groups visited') }}</small><strong>{{ number_format($stats['groups']) }}</strong><em>{{ __('Distinct groups') }}</em>
        </div>
    </div>
    <div class="card">
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
                                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.group-visits.show', $visit) }}"
                                        title="{{ __('View') }}" aria-label="{{ __('View') }}"><span
                                            class="ph ph-eye" aria-hidden="true"></span></a>
                                    <form method="POST" action="{{ route('admin.group-visits.destroy', $visit) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" title="{{ __('Delete') }}"
                                            aria-label="{{ __('Delete') }}"
                                            data-confirm="{{ __('Delete this visit record?') }}"><span
                                                class="ph ph-trash" aria-hidden="true"></span></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>{{ __('No group visits recorded yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $visits])
    </div>
@endsection
