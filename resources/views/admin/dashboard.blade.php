@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
<div class="page-head"><div><p class="eyebrow">PORTFOLIO OVERVIEW</p><h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ',auth()->user()->name)[0] }}</h1><p>Here is today’s operating position across your portfolio.</p></div><form class="head-actions" method="GET"><select name="branch_id"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId==$branch->id)>{{ $branch->branch_name }}</option>@endforeach</select><button class="btn btn-secondary">Apply filter</button></form></div>
<section class="stats">
    <div class="stat"><small>Active members</small><strong>{{ number_format($activeMembers) }}</strong><em>Current member base</em></div>
    <div class="stat"><small>Active loans</small><strong>{{ number_format($activeLoanCount) }}</strong><em>Performing portfolio</em></div>
    <div class="stat gold"><small>Outstanding portfolio</small><strong>TZS {{ number_format($portfolio) }}</strong><em>Principal + interest</em></div>
    <div class="stat"><small>Collection rate</small><strong>{{ number_format($collectionRate,1) }}%</strong><div class="progress"><span style="width:{{ min(100,$collectionRate) }}%"></span></div></div>
    <div class="stat"><small>Expected today</small><strong>TZS {{ number_format($expected) }}</strong><em>Scheduled collections</em></div>
    <div class="stat"><small>Collected today</small><strong>TZS {{ number_format($collected) }}</strong><em>Posted payments</em></div>
    <div class="stat"><small>Overdue loans</small><strong>{{ number_format($overdueLoans) }}</strong><em>Requires follow-up</em></div>
    <div class="stat gold"><small>Open applications</small><strong>{{ number_format($pendingApplications) }}</strong><em>In the approval pipeline</em></div>
</section>
<section class="grid-2">
<div class="card"><div class="card-head"><h2>Recent collections</h2><a class="table-link" href="{{ route('admin.loans.index') }}">View loans →</a></div><div class="table-wrap"><table><thead><tr><th>Member</th><th>Loan</th><th>Amount</th><th>Status</th></tr></thead><tbody>@forelse($recentPayments as $payment)<tr><td>{{ $payment->member->first_name }} {{ $payment->member->last_name }}</td><td><a class="table-link" href="{{ route('admin.loans.show',$payment->loan) }}">{{ $payment->loan->loan_number }}</a></td><td class="money">TZS {{ number_format($payment->amount) }}</td><td><span class="badge {{ $payment->status }}">{{ $payment->status }}</span></td></tr>@empty<tr><td colspan="4" class="empty">No collections posted yet today.</td></tr>@endforelse</tbody></table></div></div>
<div class="card"><div class="card-head"><h2>Quick actions</h2></div><div class="card-body"><div class="form-grid"><a class="btn btn-primary" href="{{ route('admin.members.create') }}">＋ Register member</a><a class="btn btn-gold" href="{{ route('admin.loan-applications.create') }}">＋ New application</a><a class="btn btn-secondary" href="{{ route('admin.groups.create') }}">Create group</a><a class="btn btn-secondary" href="{{ route('admin.reports.index') }}">View reports</a></div></div></div>
</section>
@endsection
