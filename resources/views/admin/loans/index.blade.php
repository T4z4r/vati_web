@extends('layouts.admin')
@section('title', 'Loans')
@section('content')
    <div class="page-head card">
        <div>
            <p class="eyebrow">{{ __('LOANS AND COLLECTIONS') }}</p>
            <h1>{{ __('Loan accounts') }}</h1>
            <p>{{ __('Review loan balances, maturity, and repayment status.') }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-secondary" href="{{ route('admin.loans.export.list', ['format' => 'pdf'] + request()->query()) }}" title="{{ __('Export PDF') }}"><span class="ph ph-file-pdf" aria-hidden="true"></span> {{ __('PDF') }}</a>
            <a class="btn btn-secondary" href="{{ route('admin.loans.export.list', ['format' => 'xlsx'] + request()->query()) }}" title="{{ __('Export Excel') }}"><span class="ph ph-file-xls" aria-hidden="true"></span> {{ __('Excel') }}</a>
        </div>
    </div>
    <div class="stats">
        <div class="stat">
            <span class="ph ph-stack stat-icon" aria-hidden="true"></span>
            <small>{{ __('Loans') }}</small><strong>{{ number_format($stats['total']) }}</strong><em>{{ __('All statuses in scope') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-money stat-icon" aria-hidden="true"></span>
            <small>{{ __('Disbursed') }}</small><strong>TZS {{ number_format($stats['disbursed'], 0) }}</strong><em>{{ __('Principal issued') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-warning stat-icon" aria-hidden="true"></span>
            <small>{{ __('Outstanding balance') }}</small><strong>TZS {{ number_format($stats['outstanding'], 0) }}</strong><em>{{ __('Principal and interest') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-trend-up stat-icon" aria-hidden="true"></span>
            <small>{{ __('Repaid to date') }}</small><strong>{{ number_format($stats['repaidShare'], 1) }}%</strong><em>{{ __('Of principal recovered') }}</em>
        </div>
    </div>
    <div class="card">
        <div class="status-tabs">
            <nav class="tabs-nav">
                @foreach ($statusTabs as $tab)
                    <a class="tab @if ($activeStatus === $tab['key']) active @endif"
                        href="{{ route('admin.loans.index', $tab['key'] === '' ? request()->except('status', 'page') : array_merge(request()->except('status', 'page'), ['status' => $tab['key']])) }}"
                        @if ($activeStatus === $tab['key']) aria-current="page" @endif>
                        <span>{{ __($tab['label']) }}</span>
                        <span class="badge @if ($tab['key'] !== '') {{ $tab['key'] }} @endif">{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
        <form class="filters"><input class="search" name="search" value="{{ request('search') }}"
                placeholder="{{ __('Loan number or member') }}"><button class="btn btn-secondary">{{ __('Filter') }}</button></form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Loan') }}</th>
                        <th>{{ __('Member / Group') }}</th>
                        <th>{{ __('Principal') }}</th>
                        <th>{{ __('Outstanding') }}</th>
                        <th>{{ __('Installment') }}</th>
                        <th>{{ __('Maturity') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="actions-col">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($loans as $loan)
                        <tr>
                            <td><a class="table-link"
                                    href="{{ route('admin.loans.show', $loan) }}">{{ $loan->loan_number }}</a><br><small>{{ $loan->product->name }}</small>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">@include('admin.partials.member-photo', [
                                    'member' => $loan->member,
                                    'size' => 44,
                                ])<div>
                                        {{ $loan->member->first_name }}
                                        {{ $loan->member->last_name }}<br><small>{{ $loan->group->group_name }}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="money">TZS {{ number_format($loan->principal_amount) }}</td>
                            <td class="money">TZS {{ number_format($loan->total_balance) }}</td>
                            <td class="money">TZS {{ number_format($loan->installment_amount) }}</td>
                            <td>{{ $loan->maturity_date?->format('d M Y') ?? '-' }}</td>
                            <td><span
                                    class="badge {{ $loan->status->value }}">{{ str_replace('_', ' ', $loan->status->value) }}</span>
                            </td>
                            <td>
                                <div class="table-actions"><a class="btn btn-sm btn-secondary"
                                        href="{{ route('admin.loans.show', $loan) }}" title="{{ __('View') }}"
                                        aria-label="{{ __('View') }}"><span class="ph ph-eye"
                                            aria-hidden="true"></span></a></div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>{{ __('No loan accounts found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $loans])
    </div>
@endsection
