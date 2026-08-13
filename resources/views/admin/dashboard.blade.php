@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('PORTFOLIO OVERVIEW') }}</p>
            <h1>{{ __('Good') }}
                {{ now()->hour < 12 ? __('morning') : (now()->hour < 17 ? __('afternoon') : __('evening')) }},
                {{ explode(' ', auth()->user()->name)[0] }}</h1>
            <p>{{ __("Here is today's operating position across your portfolio.") }}</p>
        </div>
        <form class="head-actions" method="GET"><select name="branch_id">
                <option value="">{{ __('All branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->branch_name }}</option>
                @endforeach
            </select>
            <button class="btn btn-secondary">{{ __('Apply filter') }}</button>
        </form>
    </div>
    <section class="stats">
        <div class="stat">
            <small>{{ __('Active members') }}</small><strong>{{ number_format($activeMembers) }}</strong><em>{{ __('Current member base') }}</em>
        </div>
        <div class="stat">
            <small>{{ __('Active loans') }}</small><strong>{{ number_format($activeLoanCount) }}</strong><em>{{ __('Performing portfolio') }}</em>
        </div>
        <div class="stat gold"><small>{{ __('Outstanding portfolio') }}</small><strong>TZS
                {{ number_format($portfolio) }}</strong><em>{{ __('Principal + interest') }}</em></div>
        <div class="stat">
            <small>{{ __('Collection rate') }}</small><strong>{{ number_format($collectionRate, 1) }}%</strong>
            <div class="progress"><span style="width:{{ min(100, $collectionRate) }}%"></span></div>
        </div>
        <div class="stat"><small>{{ __('Expected today') }}</small><strong>TZS
                {{ number_format($expected) }}</strong><em>{{ __('Scheduled collections') }}</em></div>
        <div class="stat"><small>{{ __('Collected today') }}</small><strong>TZS
                {{ number_format($collected) }}</strong><em>{{ __('Posted payments') }}</em></div>
        <div class="stat">
            <small>{{ __('Overdue loans') }}</small><strong>{{ number_format($overdueLoans) }}</strong><em>{{ __('Requires follow-up') }}</em>
        </div>
        <div class="stat gold">
            <small>{{ __('Open applications') }}</small><strong>{{ number_format($pendingApplications) }}</strong><em>{{ __('In the approval pipeline') }}</em>
        </div>
    </section>
    <section class="grid-2">
        <div class="card">
            <div class="card-head">
                <h2>{{ __('Recent collections') }}</h2><a class="table-link"
                    href="{{ route('admin.loans.index') }}">{{ __('View loans') }} →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Member') }}</th>
                            <th>{{ __('Loan') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayments as $payment)
                            <tr>
                                <td>{{ $payment->member->first_name }} {{ $payment->member->last_name }}</td>
                                <td><a class="table-link"
                                        href="{{ route('admin.loans.show', $payment->loan) }}">{{ $payment->loan->loan_number }}</a>
                                </td>
                                <td class="money">TZS {{ number_format($payment->amount) }}</td>
                                <td><span class="badge {{ $payment->status }}">{{ $payment->status }}</span></td>
                        </tr>@empty<tr>
                                <td colspan="4" class="empty">{{ __('No collections posted yet today.') }}</td>
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
                <div class="form-grid"><a class="btn btn-primary" href="{{ route('admin.members.create') }}">＋
                        {{ __('Register member') }}</a><a class="btn btn-gold"
                        href="{{ route('admin.loan-applications.create') }}">＋ {{ __('New application') }}</a><a
                        class="btn btn-secondary" href="{{ route('admin.groups.create') }}">{{ __('Create group') }}</a><a
                        class="btn btn-secondary" href="{{ route('admin.reports.index') }}">{{ __('View reports') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
