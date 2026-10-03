@extends('layouts.admin')
@section('title', 'Repayments')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('LOANS AND COLLECTIONS') }}</p>
            <h1>{{ __('Repayments') }}</h1>
            <p>{{ __('Every repayment collected, with daily, weekly and pending figures.') }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-secondary"
                href="{{ route('admin.repayments.export.list', ['format' => 'pdf'] + request()->query()) }}"
                title="{{ __('Export PDF') }}"><span class="ph ph-file-pdf" aria-hidden="true"></span> {{ __('PDF') }}</a>
            <a class="btn btn-secondary"
                href="{{ route('admin.repayments.export.list', ['format' => 'xlsx'] + request()->query()) }}"
                title="{{ __('Export Excel') }}"><span class="ph ph-file-xls" aria-hidden="true"></span> {{ __('Excel') }}</a>
        </div>
    </div>
    <div class="stats">
        <div class="stat">
            <span class="ph ph-calendar-check stat-icon" aria-hidden="true"></span>
            <small>{{ __("Today's repayments") }}</small>
            <strong>TZS {{ number_format($stats['todayAmount']) }}</strong>
            <em>{{ number_format($stats['today']) }} {{ __('receipts today') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-calendar-dots stat-icon" aria-hidden="true"></span>
            <small>{{ __('This week') }}</small>
            <strong>TZS {{ number_format($stats['thisWeekAmount']) }}</strong>
            <em>{{ number_format($stats['thisWeek']) }} {{ __('receipts this week') }}</em>
        </div>
        <div class="stat danger">
            <span class="ph ph-hourglass-medium stat-icon" aria-hidden="true"></span>
            <small>{{ __('Pending') }}</small>
            <strong>TZS {{ number_format($stats['pendingAmount']) }}</strong>
            <em>{{ number_format($stats['pending']) }} {{ __('installments due') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-stack stat-icon" aria-hidden="true"></span>
            <small>{{ __('Total repaid') }}</small>
            <strong>TZS {{ number_format($stats['totalAmount']) }}</strong>
            <em>{{ number_format($stats['total']) }} {{ __('posted receipts') }}</em>
        </div>
    </div>
    <div class="card">
        <div class="status-tabs">
            <nav class="tabs-nav">
                @foreach ($statusTabs as $tab)
                    <a class="tab @if ($activeStatus === $tab['key']) active @endif"
                        href="{{ route('admin.repayments.index', $tab['key'] === '' ? request()->except('status', 'page') : array_merge(request()->except('status', 'page'), ['status' => $tab['key']])) }}"
                        @if ($activeStatus === $tab['key']) aria-current="page" @endif>
                        <span>{{ __($tab['label']) }}</span>
                        <span class="badge @if ($tab['key'] !== '') {{ $tab['key'] }} @endif">{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
        <form class="filters">
            <input class="search" name="search" value="{{ request('search') }}"
                placeholder="{{ __('Receipt, loan number or member') }}">
            <select name="payment_method">
                <option value="">{{ __('All methods') }}</option>
                @foreach ($methods as $value => $label)
                    <option value="{{ $value }}" @selected($activeMethod === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" aria-label="{{ __('From date') }}">
            <input type="date" name="to" value="{{ request('to') }}" aria-label="{{ __('To date') }}">
            <select name="per_page">
                @foreach ([10, 20, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page', 20) === $size)>{{ $size }} / {{ __('page') }}</option>
                @endforeach
            </select>
            <button class="btn btn-secondary">{{ __('Filter') }}</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Receipt') }}</th>
                        <th>{{ __('Member / Group') }}</th>
                        <th>{{ __('Loan') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th>{{ __('Principal') }}</th>
                        <th>{{ __('Interest') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="actions-col">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($repayments as $repayment)
                        <tr>
                            <td>{{ $repayment->payment_number }}
                                @if ($repayment->reference_number)
                                    <br><small>{{ $repayment->reference_number }}</small>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">@include('admin.partials.member-photo', [
                                    'member' => $repayment->member,
                                    'size' => 44,
                                ])<div>
                                        {{ $repayment->member->first_name }}
                                        {{ $repayment->member->last_name }}<br><small>{{ $repayment->member->membership_number }}</small>
                                    </div>
                                </div>
                            </td>
                            <td><a class="table-link"
                                    href="{{ route('admin.loans.show', $repayment->loan) }}">{{ $repayment->loan->loan_number }}</a></td>
                            <td>{{ $repayment->paid_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td>{{ $methods[$repayment->payment_method] ?? str_replace('_', ' ', $repayment->payment_method) }}</td>
                            <td class="money">TZS {{ number_format($repayment->allocations->sum('principal_amount')) }}</td>
                            <td class="money">TZS {{ number_format($repayment->allocations->sum('interest_amount')) }}</td>
                            <td class="money">TZS {{ number_format($repayment->amount) }}</td>
                            <td><span class="badge {{ $repayment->status }}">{{ $repayment->status }}</span></td>
                            <td>
                                <div class="table-actions"><a class="btn btn-sm btn-secondary"
                                        href="{{ route('admin.loans.show', $repayment->loan) }}" title="{{ __('View') }}"
                                        aria-label="{{ __('View') }}"><span class="ph ph-eye"
                                            aria-hidden="true"></span></a></div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>{{ __('No repayments found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $repayments])
    </div>
@endsection