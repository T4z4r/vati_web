@extends('layouts.admin')
@section('title', __('Group Visit').' - '.$group->group_name)
@section('content')
    @php
        $officer = $visit->user;
        $officerInitials = collect(preg_split('/\s+/', trim($officer?->name ?? '')))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
        $outstandingBalance = (float) $group->outstanding_balance;
        $outstandingRepayment = (float) $group->outstanding_repayment;
        $repaymentProgress = $outstandingRepayment > 0 ? max(0, min(100, 100 - ($outstandingBalance / $outstandingRepayment * 100))) : 0;
        $savingsExpected = (float) $group->savings_expected;
        $savingsRate = $savingsExpected > 0 ? max(0, min(100, round((float) $group->savings_collected / $savingsExpected * 100, 1))) : 0;
        $visitLocation = $visit->location ?: $group->location;
    @endphp

    <div class="page-head card">
        <div style="display:flex;align-items:center;gap:16px">
            <span class="visit-hero-icon ph ph-map-pin" aria-hidden="true"></span>
            <div>
                <p class="eyebrow">{{ __('FIELD OPERATIONS') }} &middot; {{ $group->group_code }}</p>
                <h1>{{ $group->group_name }}</h1>
                <p>{{ $visit->visit_date->format('l, d F Y') }} &middot; {{ __('Recorded by') }} {{ $officer?->name ?? __('Unknown officer') }}@if($visitLocation) &middot; {{ $visitLocation }}@endif</p>
            </div>
        </div>
        <div class="head-actions">
            <a class="btn btn-secondary" href="{{ route('admin.group-visits.index') }}"><span class="ph ph-arrow-left" aria-hidden="true"></span> {{ __('Back') }}</a>
            <a class="btn btn-secondary" href="{{ route('admin.groups.show', $group) }}"><span class="ph ph-users-three" aria-hidden="true"></span> {{ __('View group') }}</a>
            <a class="btn btn-primary" href="{{ route('admin.group-visits.create', ['group_id' => $group->id]) }}"><span class="ph ph-plus" aria-hidden="true"></span> {{ __('Record another visit') }}</a>
            <form method="POST" action="{{ route('admin.group-visits.destroy', $visit) }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger" data-confirm="{{ __('Delete this visit record?') }}"><span class="ph ph-trash" aria-hidden="true"></span> {{ __('Delete') }}</button>
            </form>
        </div>
    </div>

    @if($nextVisit)
        <div class="visit-callout">
            <span class="ph ph-calendar-check" aria-hidden="true"></span>
            <div>
                <strong>{{ __('Next visit to this group') }}: {{ $nextVisit->visit_date->format('d M Y') }}</strong>
                <small>{{ $nextVisit->purpose ?: __('No purpose captured') }} &middot; {{ $nextVisit->user?->name ?? __('Unknown officer') }}</small>
            </div>
            <a class="btn btn-sm btn-secondary" href="{{ route('admin.group-visits.show', $nextVisit) }}">{{ __('Open') }}</a>
        </div>
    @endif

    <div class="stats">
        <div class="stat">
            <span class="ph ph-users-three stat-icon" aria-hidden="true"></span>
            <small>{{ __('Members in group') }}</small>
            <strong>{{ number_format($group->members_count) }}</strong>
            <em>{{ number_format($group->active_members_count) }} {{ __('active') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-currency-circle-dollar stat-icon" aria-hidden="true"></span>
            <small>{{ __('Group loans') }}</small>
            <strong>{{ number_format($group->loans_count) }}</strong>
            <em>{{ number_format($group->outstanding_loans_count) }} {{ __('still outstanding') }}</em>
        </div>
        <div class="stat gold">
            <span class="ph ph-warning stat-icon" aria-hidden="true"></span>
            <small>{{ __('Outstanding balance') }}</small>
            <strong>TZS {{ number_format($outstandingBalance, 2) }}</strong>
            <div class="progress"><span style="width:{{ $repaymentProgress }}%"></span></div>
        </div>
        <div class="stat">
            <span class="ph ph-piggy-bank stat-icon" aria-hidden="true"></span>
            <small>{{ __('Savings collected') }}</small>
            <strong>TZS {{ number_format((float) $group->savings_collected, 2) }}</strong>
            <em>{{ number_format($savingsRate, 1) }}% {{ __('of expected savings') }}</em>
        </div>
    </div>

    <div class="grid-2">
        <div>
            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Visit details') }}</h2>
                    <span class="badge active">{{ __('Visit') }} #{{ $visitNumber }} {{ __('of') }} {{ $totalVisits }}</span>
                </div>
                <div class="card-body">
                    <table class="detail-table">
                        <tbody>
                            <tr>
                                <th>{{ __('Group') }}</th>
                                <td><a class="table-link" href="{{ route('admin.groups.show', $group) }}">{{ $group->group_name }}</a> <span class="muted">{{ $group->group_code }}</span></td>
                            </tr>
                            <tr>
                                <th>{{ __('Visit date') }}</th>
                                <td>{{ $visit->visit_date->format('l, d F Y') }} <span class="muted">&middot; {{ $visit->visit_date->diffForHumans() }}</span></td>
                            </tr>
                            <tr>
                                <th>{{ __('Officer') }}</th>
                                <td>
                                    <span class="visit-person">
                                        <span class="avatar">{{ $officerInitials ?: '?' }}</span>
                                        <span>{{ $officer?->name ?? __('Unknown officer') }}@if($group->loanOfficer && $group->loanOfficer->is($officer))<small class="muted"> &middot; {{ __('assigned loan officer') }}</small>@endif</span>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('Purpose') }}</th>
                                <td>{{ $visit->purpose ?: __('Not specified') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Location') }}</th>
                                <td>{{ $visitLocation ?: __('Not specified') }}@if(! $visit->location && $group->location) <span class="muted">{{ __('from group profile') }}</span>@endif</td>
                            </tr>
                            <tr>
                                <th>{{ __('Recorded at') }}</th>
                                <td>{{ $visit->created_at->format('d M Y H:i') }}</td>
                            </tr>
                            @if($previousVisit)
                                <tr>
                                    <th>{{ __('Previous visit') }}</th>
                                    <td>
                                        <a class="table-link" href="{{ route('admin.group-visits.show', $previousVisit) }}">{{ $previousVisit->visit_date->format('d M Y') }}</a>
                                        <span class="muted">&middot; {{ (int) $previousVisit->visit_date->diffInDays($visit->visit_date) }} {{ __('days earlier') }} &middot; {{ $previousVisit->user?->name ?? __('Unknown officer') }}</span>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            <br>
            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Field notes') }}</h2>
                    @if($visit->notes)<span class="badge active">{{ __('Captured') }}</span>@endif
                </div>
                <div class="card-body">
                    @if($visit->notes)
                        <p class="visit-note">{{ $visit->notes }}</p>
                    @else
                        <div class="empty"><span class="ph ph-note-pencil empty-icon" aria-hidden="true"></span>{{ __('No notes were captured for this visit.') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Visit history') }}</h2>
                    <div class="head-actions">
                        <span>{{ $totalVisits }} {{ __('visits') }}</span>
                        <a class="btn btn-sm btn-secondary" href="{{ route('admin.group-visits.index', ['group_id' => $group->id]) }}">{{ __('View all') }}</a>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="visit-timeline">
                        <li class="is-current">
                            <span class="visit-date">{{ $visit->visit_date->format('d M Y') }} <span class="badge active">{{ __('This visit') }}</span></span>
                            <span class="visit-meta">{{ $visit->purpose ?: __('No purpose captured') }} &middot; {{ $officer?->name ?? __('Unknown officer') }}</span>
                        </li>
                        @foreach($history as $pastVisit)
                            <li>
                                <span class="visit-date"><a class="table-link" href="{{ route('admin.group-visits.show', $pastVisit) }}">{{ $pastVisit->visit_date->format('d M Y') }}</a></span>
                                <span class="visit-meta">{{ $pastVisit->purpose ?: __('No purpose captured') }}@if($pastVisit->location) &middot; {{ $pastVisit->location }}@endif &middot; {{ $pastVisit->user?->name ?? __('Unknown officer') }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if($totalVisits < 2)
                        <p class="muted" style="margin:14px 0 0">{{ __('This is the first visit recorded for this group.') }}</p>
                    @endif
                </div>
            </div>
            <br>
            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Group snapshot') }}</h2>
                    <span class="badge {{ $group->status ? 'active' : 'inactive' }}">{{ $group->status ? __('Active') : __('Inactive') }}</span>
                </div>
                <div class="card-body">
                    <table class="detail-table">
                        <tbody>
                            <tr>
                                <th>{{ __('Branch') }}</th>
                                <td>{{ $group->branch?->branch_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Ward / District') }}</th>
                                <td>{{ collect([$group->ward, $group->district])->filter()->implode(', ') ?: '—' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Meeting schedule') }}</th>
                                <td>{{ $group->meeting_day ?: __('Not set') }}@if($group->meeting_time) <span class="muted">{{ substr($group->meeting_time, 0, 5) }}</span>@endif</td>
                            </tr>
                            <tr>
                                <th>{{ __('Loan officer') }}</th>
                                <td>{{ $group->loanOfficer?->name ?? __('Unassigned') }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Members') }}</th>
                                <td>{{ number_format($group->members_count) }} <span class="muted">{{ number_format($group->active_members_count) }} {{ __('active') }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="form-actions">
                        <a class="btn btn-secondary" href="{{ route('admin.groups.show', $group) }}"><span class="ph ph-arrow-right" aria-hidden="true"></span> {{ __('Open group profile') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
