@extends('layouts.admin')
@section('title', 'Expected repayments')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('LOANS AND COLLECTIONS') }}</p>
            <h1>{{ __('Expected repayments') }}</h1>
            <p>{{ __('Repayments scheduled for') }} {{ $collectionDate->format('d M Y') }}.</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-secondary" href="{{ route('admin.repayments.index', request()->except('collection_date')) }}">
                <span class="ph ph-receipt" aria-hidden="true"></span> {{ __('Posted repayments') }}
            </a>
        </div>
    </div>

    <div class="stats">
        <div class="stat">
            <span class="ph ph-calendar-check stat-icon" aria-hidden="true"></span>
            <small>{{ __('Expected') }}</small>
            <strong>TZS {{ number_format($stats['expectedAmount']) }}</strong>
            <em>{{ number_format($stats['total']) }} {{ __('scheduled installments') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-users stat-icon" aria-hidden="true"></span>
            <small>{{ __('Expected borrowers') }}</small>
            <strong>{{ number_format($stats['borrowers']) }}</strong>
            <em>{{ __('people expected to repay') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-check-circle stat-icon" aria-hidden="true"></span>
            <small>{{ __('Paid') }}</small>
            <strong>TZS {{ number_format($stats['paidAmount']) }}</strong>
            <em>{{ number_format($stats['paid']) }} {{ __('completed') }} · {{ number_format($stats['partial']) }} {{ __('partial') }}</em>
        </div>
        <div class="stat danger">
            <span class="ph ph-hourglass-medium stat-icon" aria-hidden="true"></span>
            <small>{{ __('Outstanding') }}</small>
            <strong>TZS {{ number_format($stats['outstandingAmount']) }}</strong>
            <em>{{ number_format($stats['pending']) }} {{ __('pending') }}</em>
        </div>
    </div>

    <div class="card">
        <form class="filters">
            @if (request()->filled('branch_id'))
                <input type="hidden" name="branch_id" value="{{ request('branch_id') }}">
            @endif
            <input class="search" name="search" value="{{ request('search') }}"
                placeholder="{{ __('Loan number, member or group') }}">
            <input type="date" name="collection_date" value="{{ $collectionDate->toDateString() }}"
                aria-label="{{ __('Collection date') }}">
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
                        <th>{{ __('Member / Group') }}</th>
                        <th>{{ __('Loan') }}</th>
                        <th>{{ __('Installment') }}</th>
                        <th>{{ __('Due date') }}</th>
                        <th>{{ __('Expected') }}</th>
                        <th>{{ __('Paid') }}</th>
                        <th>{{ __('Outstanding') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="actions-col">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($installments as $installment)
                        @php
                            $loan = $installment->loan;
                            $member = $loan?->member;
                            $balance = max(0, (float) $installment->total_due - (float) $installment->total_paid - (float) $installment->interest_exemption);
                        @endphp
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">@include('admin.partials.member-photo', [
                                    'member' => $member,
                                    'size' => 44,
                                ])<div>
                                        {{ $member?->first_name }} {{ $member?->last_name }}<br>
                                        <small>{{ $member?->membership_number }} · {{ $loan?->group?->group_name }}</small>
                                    </div>
                                </div>
                            </td>
                            <td><a class="table-link" href="{{ route('admin.loans.show', $loan) }}">{{ $loan?->loan_number }}</a></td>
                            <td>{{ $installment->installment_number }}</td>
                            <td>{{ $installment->due_date?->format('d M Y') }}</td>
                            <td class="money">TZS {{ number_format($installment->total_due, 2) }}</td>
                            <td class="money">TZS {{ number_format($installment->total_paid, 2) }}</td>
                            <td class="money">TZS {{ number_format($balance, 2) }}</td>
                            <td><span class="badge {{ $installment->status }}">{{ str_replace('_', ' ', $installment->status) }}</span></td>
                            <td>
                                <div class="table-actions"><a class="btn btn-sm btn-secondary"
                                        href="{{ route('admin.loans.show', $loan) }}" title="{{ __('View loan') }}"
                                        aria-label="{{ __('View loan') }}"><span class="ph ph-eye"
                                            aria-hidden="true"></span></a></div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>{{ __('No expected repayments for this date.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4">{{ __('Totals') }}</th>
                        <th class="money">TZS {{ number_format($stats['expectedAmount'], 2) }}</th>
                        <th class="money">TZS {{ number_format($stats['paidAmount'], 2) }}</th>
                        <th class="money">TZS {{ number_format($stats['outstandingAmount'], 2) }}</th>
                        <th colspan="2">{{ number_format($stats['total']) }} {{ __('installments') }} &middot; {{ number_format($stats['borrowers']) }} {{ \Illuminate\Support\Str::plural(__('borrower'), $stats['borrowers']) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $installments])
    </div>
@endsection
