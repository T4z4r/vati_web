@extends('layouts.admin')
@section('title', $group->group_name)
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ $group->group_code }}</p>
            <h1>{{ $group->group_name }}</h1>
            <p>{{ $group->branch->branch_name }} &middot; {{ $group->location ?: __('Location not recorded') }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-secondary" href="{{ route('admin.groups.index') }}"><span class="ph ph-arrow-left" aria-hidden="true"></span> {{ __('Back') }}</a>
            <a class="btn btn-primary" href="{{ route('admin.members.create', ['group_id' => $group->id]) }}"><span class="ph ph-user-plus" aria-hidden="true"></span>
                {{ __('Register member') }}</a>
            @can('edit-groups')
                <a class="btn btn-secondary" href="{{ route('admin.groups.edit', $group) }}">{{ __('Edit') }}</a>
            @endcan
            @can('delete-groups')
                <form method="POST" action="{{ route('admin.groups.destroy', $group) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger"
                        data-confirm="{{ __('Delete this group? Groups with members or lending history cannot be deleted.') }}">{{ __('Delete') }}</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="stats">
        <div class="stat"><small>{{ __('Total members') }}</small><strong>{{ $group->members_count }}</strong></div>
        <div class="stat">
            <small>{{ __('Loan applications') }}</small><strong>{{ $group->loan_applications_count }}</strong></div>
        <div class="stat gold"><small>{{ __('Loans originated') }}</small><strong>{{ $group->loans_count }}</strong></div>
        <div class="stat"><small>{{ __('Meeting schedule') }}</small><strong
                style="font-size:16px">{{ $group->meeting_day ?: __('Not set') }}</strong><em>{{ $group->meeting_time ? substr($group->meeting_time, 0, 5) : '' }}</em>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h2>{{ __('Members and loan balances') }}</h2>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Member') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Current loans') }}</th>
                            <th>{{ __('Outstanding balance') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($members as $member)
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">@include('admin.partials.member-photo', [
                                        'member' => $member,
                                        'size' => 48,
                                    ])<div>
                                            <a class="table-link"
                                                href="{{ route('admin.members.show', $member) }}">{{ $member->first_name }}
                                                {{ $member->last_name }}</a><br><small>{{ $member->membership_number }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $member->phone }}</td>
                                <td><span class="badge {{ $member->status }}">{{ $member->status }}</span></td>
                                <td>{{ $member->current_loans_count }}</td>
                                <td><strong>TZS
                                        {{ number_format((float) ($member->outstanding_loan_balance ?? 0), 2) }}</strong>
                                </td>
                                <td>
                                    @can('view-members')
                                        <a class="btn btn-sm btn-secondary"
                                            href="{{ route('admin.members.show', $member) }}">{{ __('View details') }}</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>{{ __('No registered members.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <h2 class="section-title">{{ __('Operating details') }}</h2>
            <table class="detail-table">
                <tbody>
                    <tr>
                        <th>{{ __('Loan officer') }}</th><td>{{ $group->loanOfficer?->name ?? __('Unassigned') }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Branch') }}</th><td>{{ $group->branch->branch_name }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Ward') }}</th><td>{{ $group->ward ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('District') }}</th><td>{{ $group->district ?: '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
