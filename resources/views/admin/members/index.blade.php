@extends('layouts.admin')
@section('title', 'Members')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('MEMBER MANAGEMENT') }}</p>
            <h1>{{ __('Members') }}</h1>
            <p>{{ __('Search and manage registered VATI members.') }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-primary" href="{{ route('admin.members.create') }}"><span class="ph ph-user-plus" aria-hidden="true"></span> {{ __('Register member') }}</a>
            <a class="btn btn-secondary" href="{{ route('admin.members.export.list', ['format' => 'pdf'] + request()->query()) }}" title="{{ __('Export PDF') }}"><span class="ph ph-file-pdf" aria-hidden="true"></span> {{ __('PDF') }}</a>
            <a class="btn btn-secondary" href="{{ route('admin.members.export.list', ['format' => 'xlsx'] + request()->query()) }}" title="{{ __('Export Excel') }}"><span class="ph ph-file-xls" aria-hidden="true"></span> {{ __('Excel') }}</a>
        </div>
    </div>
    <div class="stats">
        <div class="stat">
            <span class="ph ph-users stat-icon" aria-hidden="true"></span>
            <small>{{ __('Members') }}</small><strong>{{ number_format($stats['total']) }}</strong><em>{{ __('Matching the current filters') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-check-circle stat-icon" aria-hidden="true"></span>
            <small>{{ __('Active members') }}</small><strong>{{ number_format($stats['active']) }}</strong><em>{{ number_format($stats['activeShare'], 1) }}% {{ __('of the member list') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-user-plus stat-icon" aria-hidden="true"></span>
            <small>{{ __('Members registered today') }}</small><strong>{{ number_format($stats['newToday']) }}</strong><em>{{ __('Since midnight') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-users-four stat-icon" aria-hidden="true"></span>
            <small>{{ __('Groups covered') }}</small><strong>{{ number_format($stats['groups']) }}</strong><em>{{ __('Groups with members') }}</em>
        </div>
    </div>
    <div class="card">
        <div class="status-tabs">
            <nav class="tabs-nav">
                @foreach ($loanTabs as $tab)
                    <a class="tab @if ($activeLoanTab === $tab['key']) active @endif"
                        href="{{ route('admin.members.index', $tab['key'] === '' ? request()->except('loan', 'page') : array_merge(request()->except('loan', 'page'), ['loan' => $tab['key']])) }}"
                        @if ($activeLoanTab === $tab['key']) aria-current="page" @endif>
                        <span>{{ __($tab['label']) }}</span>
                        <span class="badge">{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
        <form class="filters"><input class="search" name="search" value="{{ request('search') }}"
                placeholder="{{ __('Search name, number or phone') }}">
            <input type="hidden" name="loan" value="{{ request('loan') }}">
            <select name="group_id">
                <option value="">{{ __('All groups') }}</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}" @selected(request('group_id') == $group->id)>{{ $group->group_name }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (['active', 'inactive', 'suspended', 'closed'] as $s)
                    <option @selected(request('status') == $s)>{{ $s }}</option>
                @endforeach
            </select>
            <select name="per_page">
                @foreach ([10, 20, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }} / {{ __('page') }}</option>
                @endforeach
            </select><button class="btn btn-secondary">{{ __('Filter') }}</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Contact') }}</th>
                        <th>{{ __('Group') }}</th>
                        <th>{{ __('Branch') }}</th>
                        <th>{{ __('Joined') }}</th>
                        <th>{{ __('Created at') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="actions-col">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                        @php
                            $memberName = collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' ');
                            $displayName = $memberName !== '' ? $memberName : $member->membership_number;
                        @endphp
                        <tr>
                            <td>
                                <div class="member-list-profile">
                                    @include('admin.partials.member-photo', ['member' => $member, 'size' => 44])
                                    <div>
                                        <a class="table-link"
                                            href="{{ route('admin.members.show', $member) }}">{{ $displayName }}</a><br><small
                                            class="muted">{{ $member->membership_number }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $member->phone ?: '—' }}</td>
                            <td>{{ $member->group?->group_name ?: 'Unassigned' }}</td>
                            <td>{{ $member->branch?->branch_name ?: 'Unassigned' }}</td>
                            <td>{{ $member->admission_date?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $member->created_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td><span class="badge {{ $member->status }}">{{ $member->status }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-secondary"
                                        href="{{ route('admin.members.show', $member) }}" title="{{ __('View') }}"
                                        aria-label="{{ __('View') }}"><span class="ph ph-eye"
                                            aria-hidden="true"></span></a>
                                    @can('edit-members')
                                        <a class="btn btn-sm btn-primary"
                                            href="{{ route('admin.members.edit', $member) }}" title="{{ __('Edit') }}"
                                            aria-label="{{ __('Edit') }}"><span class="ph ph-pencil-simple"
                                                aria-hidden="true"></span></a>
                                    @endcan
                                    @can('delete-members')
                                        <form method="POST" action="{{ route('admin.members.destroy', $member) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger" title="{{ __('Delete') }}"
                                                aria-label="{{ __('Delete') }}"
                                                data-confirm="{{ __('Delete this member? Members with loan history cannot be deleted. Choosing \'Delete forever\' permanently deletes the member and ALL linked data.') }}"
                                                data-force-text="{{ __('Delete forever') }}" data-trash-text="{{ __('Move to trash') }}"><span
                                                    class="ph ph-trash" aria-hidden="true"></span></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>{{ __('No members match your filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $members])
    </div>
@endsection
