@extends('layouts.admin')
@section('title', __('Dashboard'))
@section('content')
    <div class="page-head dashboard-hero">
        <div>
            <p class="eyebrow">{{ __('CREDIT REVIEW') }}</p>
            <h1>{{ __('Good') }}
                {{ now()->hour < 12 ? __('morning') : (now()->hour < 17 ? __('afternoon') : __('evening')) }},
                {{ explode(' ', auth()->user()->name)[0] }}</h1>
            <p>{{ __('Your credit review pipeline and daily targets.') }}</p>
        </div>
    </div>

    <section class="stats">
        <div class="stat">
            <span class="ph ph-clock stat-icon" aria-hidden="true"></span>
            <small>{{ __('Pending review') }}</small>
            <strong>{{ number_format($pendingCreditReview) }}</strong>
            <em>{{ __('{{ $newAssignments }} new', ['new' => $newAssignments]) }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-check-circle stat-icon" aria-hidden="true"></span>
            <small>{{ __('Reviewed today') }}</small>
            <strong>{{ number_format($reviewedToday) }}</strong>
            <em>{{ __('Today') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-arrow-u-up-left stat-icon" aria-hidden="true"></span>
            <small>{{ __('Returned cases') }}</small>
            <strong>{{ number_format($returnedCases) }}</strong>
            <em>{{ __('Requires action') }}</em>
        </div>
        <div class="stat danger">
            <span class="ph ph-warning stat-icon" aria-hidden="true"></span>
            <small>{{ __('High risk') }}</small>
            <strong>{{ number_format($highRiskCases) }}</strong>
            <em>{{ __('Review') }}</em>
        </div>
    </section>

    <section class="stats" style="margin-bottom:24px">
        <div class="stat">
            <span class="ph ph-wallet stat-icon" aria-hidden="true"></span>
            <small>{{ __('Gross loan portfolio') }}</small>
            <strong>TZS {{ number_format((float) $portfolio['gross_loan_portfolio']) }}</strong>
        </div>
        <div class="stat">
            <span class="ph ph-squares-four stat-icon" aria-hidden="true"></span>
            <small>{{ __('Active loans') }}</small>
            <strong>{{ number_format($portfolio['active_loans']) }}</strong>
        </div>
        <div class="stat">
            <span class="ph ph-crosshair stat-icon" aria-hidden="true"></span>
            <small>{{ __('Collection rate') }}</small>
            <strong>{{ number_format((float) $portfolio['collection_rate'], 1) }}%</strong>
            <div class="progress"><span style="width:{{ min(100, (float) $portfolio['collection_rate']) }}%"></span></div>
        </div>
        <div class="stat">
            <span class="ph ph-chart-line-up stat-icon" aria-hidden="true"></span>
            <small>{{ __('Portfolio at risk') }}</small>
            <strong>{{ number_format((float) $portfolio['portfolio_at_risk'], 1) }}%</strong>
        </div>
    </section>

    <section class="grid-2">
        <div class="card">
            <div class="card-head">
                <h2>{{ __('Priority applications') }}</h2>
                <a class="table-link" href="{{ route('admin.loan-applications.index', ['status' => 'submitted']) }}">{{ __('View all') }} <span class="ph ph-arrow-right" aria-hidden="true"></span></a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Application') }}</th>
                            <th>{{ __('Member') }}</th>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($priorityApplications as $application)
                            <tr>
                                <td><a class="table-link" href="{{ route('admin.loan-applications.show', $application) }}">{{ $application->application_number }}</a></td>
                                <td>{{ $application->member->first_name }} {{ $application->member->last_name }}</td>
                                <td>{{ $application->product->name }}</td>
                                <td class="money">TZS {{ number_format($application->requested_amount) }}</td>
                                <td><span class="badge {{ $application->status->value }}">{{ str_replace('_', ' ', $application->status->value) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty">{{ __('No pending applications.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-head">
                <h2>{{ __('Quick actions') }}</h2>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <a class="btn btn-primary" href="{{ route('admin.loan-applications.index', ['status' => 'submitted']) }}"><span class="ph ph-magnifying-glass" aria-hidden="true"></span> {{ __('Review applications') }}</a>
                    <a class="btn btn-secondary" href="{{ route('admin.loan-applications.index') }}"><span class="ph ph-file-text" aria-hidden="true"></span> {{ __('All applications') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
