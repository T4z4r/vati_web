@extends('layouts.admin')
@section('title', __('Repayment holidays'))
@section('content')
<div class="page-head"><div><h1>{{ __('Repayment holidays') }}</h1>
<p>{{ __('Holiday repayments move seven days forward, repeating if necessary. Later installments move forward to prevent overlapping dates.') }}</p>
<a href="{{ route('admin.system.settings') }}">{{ __('System Settings') }}</a></div></div>
@if($errors->any())<div class="card card-body">{{ $errors->first() }}</div>@endif
<form class="card card-body" method="POST" action="{{ route('admin.system.holidays.import') }}">
@csrf
<h2>{{ __('Add Tanzania holidays') }}</h2>
<p>{{ __('Adds fixed public holidays, Good Friday and Easter Monday. Add confirmed Eid, Maulid and specially declared holidays below. Existing dates are preserved.') }}</p>
<label>{{ __('Year') }}<input type="number" name="year" min="2000" max="2100" value="{{ now()->year }}" required></label>
<button class="btn btn-primary">{{ __('Add Tanzania holidays') }}</button>
</form>
<form class="card card-body" method="POST" action="{{ route('admin.system.holidays.store') }}">
@csrf
<h2>{{ __('Add or update a holiday') }}</h2>
<label>{{ __('Date') }}<input type="date" name="date" required></label>
<label>{{ __('Name') }}<input name="name" maxlength="255" required></label>
<button class="btn btn-primary">{{ __('Save holiday') }}</button>
</form>
<div class="card card-body"><h2>{{ __('Holiday calendar') }}</h2>
<table><thead><tr><th>{{ __('Date') }}</th><th>{{ __('Name') }}</th><th></th></tr></thead><tbody>
@foreach($holidays as $holiday)<tr><td>{{ $holiday->date->format('d M Y') }}</td><td>{{ $holiday->name }}</td><td>
<form method="POST" action="{{ route('admin.system.holidays.destroy', $holiday) }}">@csrf @method('DELETE')<button class="btn btn-secondary">{{ __('Remove') }}</button></form>
</td></tr>@endforeach
</tbody></table></div>
<form class="card card-body" method="POST" action="{{ route('admin.system.holidays.correct') }}">
@csrf
<h2>{{ __('Forward existing repayment schedules') }}</h2>
<p>{{ __('Select the holiday date range to correct. All later installments on affected loans may move to preserve their order. Receipts, payment dates, allocations, balances and paid statuses are preserved. Run this after adding holidays; saving settings alone does not change existing loans.') }}</p>
<label>{{ __('From') }}<input type="date" name="from" required></label>
<label>{{ __('To') }}<input type="date" name="to" required></label>
<label><input type="checkbox" name="include_paid" value="1">{{ __('Also correct paid and partially paid installment dates, including settled loans') }}</label>
<button class="btn btn-primary">{{ __('Forward schedules') }}</button>
</form>
@endsection
