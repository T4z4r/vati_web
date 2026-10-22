@extends('layouts.admin')
@section('title', __('Clear repayments by date'))
@section('content')
<div class="page-head"><div><h1>{{ __('Clear repayments by date') }}</h1>
<p>{{ __('Select the payment date, not the installment due date. Clearing reverses posted repayments and restores loan and installment balances. Receipts remain available under Reversed.') }}</p>
<a href="{{ route('admin.repayments.index') }}">{{ __('Repayments') }}</a></div></div>
@if($errors->any())<div class="card card-body">{{ $errors->first() }}</div>@endif
<form class="card card-body" method="GET" action="{{ route('admin.repayments.clear') }}">
<div class="form-grid">
<label>{{ __('Payment date') }}<input type="date" name="date" value="{{ old('date', request('date')) }}" required></label>
<label>{{ __('Branch') }}<select name="branch_id"><option value="">{{ __('All branches') }}</option>
@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->branch_name }}</option>@endforeach
</select></label></div>
<button class="btn btn-secondary">{{ __('Preview repayments') }}</button>
</form>
@if($filters)
<div class="card card-body">
<h2>{{ __('Preview') }}: {{ $filters['date'] }}</h2>
<p>{{ number_format($count) }} {{ __('posted repayments') }} ? TZS {{ number_format($amount, 2) }}</p>
<table><thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Loan') }}</th><th>{{ __('Member') }}</th><th>{{ __('Amount') }}</th></tr></thead><tbody>
@foreach($payments as $payment)<tr><td>{{ $payment->payment_number }}</td><td>{{ $payment->loan?->loan_number }}</td><td>{{ $payment->member?->first_name }} {{ $payment->member?->last_name }}</td><td>{{ number_format($payment->amount, 2) }}</td></tr>@endforeach
</tbody></table>
{{ $payments->links() }}
@if($count > 0)
<form method="POST" action="{{ route('admin.repayments.clear.store') }}">
@csrf
<input type="hidden" name="date" value="{{ $filters['date'] }}">
<input type="hidden" name="branch_id" value="{{ $filters['branch_id'] ?? '' }}">
<p>{{ __('This clears all posted repayments matching this date and branch, including any added since the preview.') }}</p>
<label>{{ __('Reason') }}<textarea name="reason" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea></label>
<button class="btn btn-danger">{{ __('Clear all matching repayments') }}</button>
</form>
@endif
</div>
@endif
@endsection
