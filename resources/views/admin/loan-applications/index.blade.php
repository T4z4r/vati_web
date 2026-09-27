@extends('layouts.admin')
@section('title', 'Loan Applications')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('LOAN ORIGINATION') }}</p>
            <h1>{{ __('Loan applications') }}</h1>
            <p>{{ __('Track applications from draft through approval and disbursement.') }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-primary" href="{{ route('admin.loan-applications.create') }}"><span class="ph ph-note-pencil" aria-hidden="true"></span> {{ __('New application') }}</a>
            <a class="btn btn-secondary" href="{{ route('admin.loan-applications.export.list', ['format' => 'pdf'] + request()->query()) }}" title="{{ __('Export PDF') }}"><span class="ph ph-file-pdf" aria-hidden="true"></span> {{ __('PDF') }}</a>
            <a class="btn btn-secondary" href="{{ route('admin.loan-applications.export.list', ['format' => 'xlsx'] + request()->query()) }}" title="{{ __('Export Excel') }}"><span class="ph ph-file-xls" aria-hidden="true"></span> {{ __('Excel') }}</a>
            @role('super_admin|head_office_admin')
                <form method="POST" action="{{ route('admin.loan-applications.correct-repayments') }}">
                    @csrf
                    @if (request('branch_id'))
                        <input type="hidden" name="branch_id" value="{{ request('branch_id') }}">
                    @endif
                    <button class="btn btn-secondary" data-confirm="{{ __('Recompute repayment values for loan applications and loans?') }}">
                        <span class="ph ph-calculator" aria-hidden="true"></span> {{ __('Auto correct repayments') }}
                    </button>
                </form>
            @endrole
        </div>
    </div>
    <div class="stats">
        <div class="stat">
            <span class="ph ph-file-text stat-icon" aria-hidden="true"></span>
            <small>{{ __('Applications') }}</small><strong>{{ number_format($stats['total']) }}</strong><em>{{ __('All statuses in scope') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-hourglass stat-icon" aria-hidden="true"></span>
            <small>{{ __('Awaiting review') }}</small><strong>{{ number_format($stats['inReview']) }}</strong><em>{{ __('Submitted or in review') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-seal-check stat-icon" aria-hidden="true"></span>
            <small>{{ __('Approved') }}</small><strong>{{ number_format($stats['approved']) }}</strong><em>{{ __('Awaiting disbursement') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-money stat-icon" aria-hidden="true"></span>
            <small>{{ __('Amount requested') }}</small><strong>TZS {{ number_format($stats['requested'], 0) }}</strong><em>{{ __('Requested to date') }}</em>
        </div>
    </div>
    <div class="card">
        <div class="status-tabs">
            <nav class="tabs-nav">
                @foreach ($statusTabs as $tab)
                    <a class="tab @if ($activeStatus === $tab['key']) active @endif"
                        href="{{ route('admin.loan-applications.index', $tab['key'] === '' ? request()->except('status', 'page') : array_merge(request()->except('status', 'page'), ['status' => $tab['key']])) }}"
                        @if ($activeStatus === $tab['key']) aria-current="page" @endif>
                        <span>{{ __($tab['label']) }}</span>
                        <span class="badge @if ($tab['key'] !== '') {{ $tab['key'] }} @endif">{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
        <form class="filters"><input class="search" name="search" value="{{ request('search') }}"
                placeholder="{{ __('Application or member name') }}"><button class="btn btn-secondary">{{ __('Filter') }}</button></form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Application') }}</th>
                        <th>{{ __('Member / Group') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Requested') }}</th>
                        <th>{{ __('Duration') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="actions-col">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $application)
                        @php($status = $application->status->value)
                        <tr>
                            <td><a class="table-link"
                                    href="{{ route('admin.loan-applications.show', $application) }}">{{ $application->application_number }}</a>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">@include('admin.partials.member-photo', [
                                    'member' => $application->member,
                                    'size' => 44,
                                ])<div>
                                        {{ $application->member->first_name }}
                                        {{ $application->member->last_name }}<br><small
                                            class="muted">{{ $application->group->group_name }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $application->product->name }}</td>
                            <td class="money">TZS {{ number_format($application->requested_amount) }}</td>
                            <td>{{ $application->duration_months }} {{ __('months') }}</td>
                            <td>{{ $application->created_at->format('d M Y') }}</td>
                            <td><span class="badge {{ $status }}">{{ str_replace('_', ' ', $status) }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-secondary"
                                        href="{{ route('admin.loan-applications.show', $application) }}" title="{{ __('View') }}"
                                        aria-label="{{ __('View') }}"><span class="ph ph-eye"
                                            aria-hidden="true"></span></a>
                                    @can('create-loan-applications')
                                        @if (in_array($status, ['draft', 'submitted', 'reverted'], true))
                                            <a class="btn btn-sm btn-primary"
                                                href="{{ route('admin.loan-applications.edit', $application) }}" title="{{ __('Edit') }}"
                                                aria-label="{{ __('Edit') }}"><span class="ph ph-pencil-simple"
                                                    aria-hidden="true"></span></a>
                                        @endif
                                        @if (in_array($status, ['draft', 'submitted', 'reverted', 'rejected', 'cancelled'], true) && !$application->loan)
                                            <form method="POST"
                                                action="{{ route('admin.loan-applications.destroy', $application) }}">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-danger" title="{{ __('Delete') }}"
                                                    aria-label="{{ __('Delete') }}"
                                                    data-confirm="{{ __('Delete this loan application?') }}"
                                                    data-force-text="{{ __('Delete forever') }}"><span
                                                        class="ph ph-trash" aria-hidden="true"></span></button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>{{ __('No applications found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $applications])
    </div>
@endsection
