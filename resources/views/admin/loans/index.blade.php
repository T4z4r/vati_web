@extends('layouts.admin')
@section('title', 'Loans')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('LOANS AND COLLECTIONS') }}</p>
            <h1>{{ __('Loan accounts') }}</h1>
            <p>{{ __('Review loan balances, maturity, and repayment status.') }}</p>
        </div>
    </div>
    <form class="filters"><input class="search" name="search" value="{{ request('search') }}"
            placeholder="{{ __('Loan number or member') }}"><select name="status">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (['pending_disbursement', 'active', 'overdue', 'settled', 'refinanced', 'written_off'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ str_replace('_', ' ', ucfirst($s)) }}
                </option>
            @endforeach
        </select><button class="btn btn-secondary">{{ __('Filter') }}</button></form>
    <div class="card">
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
                            <td>{{ $loan->member->first_name }}
                                {{ $loan->member->last_name }}<br><small>{{ $loan->group->group_name }}</small></td>
                            <td class="money">TZS {{ number_format($loan->principal_amount) }}</td>
                            <td class="money">TZS {{ number_format($loan->total_balance) }}</td>
                            <td class="money">TZS {{ number_format($loan->installment_amount) }}</td>
                            <td>{{ $loan->maturity_date?->format('d M Y') ?? '-' }}</td>
                            <td><span
                                    class="badge {{ $loan->status->value }}">{{ str_replace('_', ' ', $loan->status->value) }}</span>
                            </td>
                            <td>
                                <div class="table-actions"><a class="btn btn-sm btn-secondary"
                                        href="{{ route('admin.loans.show', $loan) }}">{{ __('View') }}</a></div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty">{{ __('No loan accounts found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pagination', ['paginator' => $loans])
    </div>
@endsection
