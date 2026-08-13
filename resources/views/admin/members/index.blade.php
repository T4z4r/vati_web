@extends('layouts.admin')
@section('title', 'Members')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('MEMBER MANAGEMENT') }}</p>
            <h1>{{ __('Members') }}</h1>
            <p>{{ __('Search and manage registered VATI members.') }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.members.create') }}">+ {{ __('Register member') }}</a>
    </div>
    <form class="filters"><input class="search" name="search" value="{{ request('search') }}"
            placeholder="{{ __('Search name, number or phone') }}"><select name="group_id">
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
        </select><button class="btn btn-secondary">{{ __('Filter') }}</button>
    </form>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Contact') }}</th>
                        <th>{{ __('Group') }}</th>
                        <th>{{ __('Branch') }}</th>
                        <th>{{ __('Joined') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="actions-col">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                        <tr>
                            <td><a class="table-link"
                                    href="{{ route('admin.members.show', $member) }}">{{ $member->first_name }}
                                    {{ $member->middle_name }} {{ $member->last_name }}</a><br><small
                                    class="muted">{{ $member->membership_number }}</small></td>
                            <td>{{ $member->phone }}</td>
                            <td>{{ $member->group->group_name }}</td>
                            <td>{{ $member->branch->branch_name }}</td>
                            <td>{{ $member->admission_date?->format('d M Y') ?? $member->created_at->format('d M Y') }}
                            </td>
                            <td><span class="badge {{ $member->status }}">{{ $member->status }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-secondary"
                                        href="{{ route('admin.members.show', $member) }}">{{ __('View') }}</a>
                                    @can('edit-members')
                                        <a class="btn btn-sm btn-primary"
                                            href="{{ route('admin.members.edit', $member) }}">{{ __('Edit') }}</a>
                                    @endcan
                                    @can('delete-members')
                                        <form method="POST" action="{{ route('admin.members.destroy', $member) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger"
                                                data-confirm="{{ __('Delete this member? Members with loan history cannot be deleted.') }}">{{ __('Delete') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">{{ __('No members match your filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $members])
    </div>
@endsection
