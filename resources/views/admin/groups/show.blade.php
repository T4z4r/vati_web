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
        <div class="grid-stack">
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

            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Loans') }}</h2>
                    <span class="badge">{{ $group->loans_count }}</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('Loan') }}</th>
                                <th>{{ __('Member') }}</th>
                                <th>{{ __('Principal') }}</th>
                                <th>{{ __('Outstanding') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="actions-col">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loans as $loan)
                                <tr>
                                    <td>
                                        <a class="table-link"
                                            href="{{ route('admin.loans.show', $loan) }}">{{ $loan->loan_number }}</a><br><small>{{ $loan->product->name }}</small>
                                    </td>
                                    <td>
                                        <a class="table-link"
                                            href="{{ route('admin.members.show', $loan->member) }}">{{ $loan->member->first_name }}
                                            {{ $loan->member->last_name }}</a><br><small>{{ $loan->member->membership_number }}</small>
                                    </td>
                                    <td class="money">TZS {{ number_format((float) $loan->principal_amount, 2) }}</td>
                                    <td class="money">TZS {{ number_format((float) $loan->total_balance, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $loan->status->value }}">{{ str_replace('_', ' ', $loan->status->value) }}</span>
                                    </td>
                                    <td class="actions-col">
                                        <div class="table-actions">
                                            <a class="btn btn-sm btn-secondary"
                                                href="{{ route('admin.loans.show', $loan) }}" title="{{ __('View') }}"
                                                aria-label="{{ __('View') }}"><span class="ph ph-eye"
                                                    aria-hidden="true"></span></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="empty"><span class="ph ph-tray empty-icon"
                                            aria-hidden="true"></span>{{ __('No loans recorded for this group yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Loan applications') }}</h2>
                    <span class="badge">{{ $group->loan_applications_count }}</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('Application') }}</th>
                                <th>{{ __('Member') }}</th>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('Requested') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="actions-col">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($applications as $application)
                                @php($status = $application->status->value)
                                <tr>
                                    <td>
                                        <a class="table-link"
                                            href="{{ route('admin.loan-applications.show', $application) }}">{{ $application->application_number }}</a><br><small>{{ $application->created_at->format('d M Y') }}</small>
                                    </td>
                                    <td>
                                        <a class="table-link"
                                            href="{{ route('admin.members.show', $application->member) }}">{{ $application->member->first_name }}
                                            {{ $application->member->last_name }}</a><br><small>{{ $application->member->membership_number }}</small>
                                    </td>
                                    <td>{{ $application->product->name }}</td>
                                    <td class="money">TZS {{ number_format((float) $application->requested_amount, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $status }}">{{ str_replace('_', ' ', $status) }}</span>
                                    </td>
                                    <td class="actions-col">
                                        <div class="table-actions">
                                            <a class="btn btn-sm btn-secondary"
                                                href="{{ route('admin.loan-applications.show', $application) }}" title="{{ __('View') }}"
                                                aria-label="{{ __('View') }}"><span class="ph ph-eye"
                                                    aria-hidden="true"></span></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="empty"><span class="ph ph-tray empty-icon"
                                            aria-hidden="true"></span>{{ __('No loan applications for this group yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="grid-stack">
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

            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Group Visits') }}</h2>
                    <span class="badge">{{ $group->visits_count }}</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Officer') }}</th>
                                <th>{{ __('Purpose') }}</th>
                                <th class="actions-col">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($visits as $visit)
                                <tr>
                                    <td>{{ $visit->visit_date->format('d M Y') }}</td>
                                    <td>{{ $visit->user?->name ?? __('Unassigned') }}</td>
                                    <td>{{ $visit->purpose ?: '—' }}</td>
                                    <td class="actions-col">
                                        <div class="table-actions">
                                            <a class="btn btn-sm btn-secondary"
                                                href="{{ route('admin.group-visits.show', $visit) }}" title="{{ __('View') }}"
                                                aria-label="{{ __('View') }}"><span class="ph ph-eye"
                                                    aria-hidden="true"></span></a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="empty"><span class="ph ph-tray empty-icon"
                                            aria-hidden="true"></span>{{ __('No group visits recorded yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
