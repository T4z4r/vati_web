@extends('layouts.admin')
@section('title',$loan->loan_number)
@section('content')
@php($status=$loan->status->value)
<div class="page-head"><div><p class="eyebrow">{{ $loan->loan_number }}</p><h1>{{ $loan->member->first_name }} {{ $loan->member->last_name }}</h1><p>{{ $loan->product->name }} · {{ $loan->group->group_name }}</p></div><span class="badge {{ $status }}">{{ str_replace('_',' ',$status) }}</span></div>
<div class="stats"><div class="stat gold"><small>Principal</small><strong>TZS {{ number_format($loan->principal_amount) }}</strong></div><div class="stat"><small>Total repayment</small><strong>TZS {{ number_format($loan->total_repayment) }}</strong></div><div class="stat"><small>Outstanding</small><strong>TZS {{ number_format($loan->total_balance) }}</strong><div class="progress"><span style="width:{{ $loan->total_repayment?max(0,100-$loan->total_balance/$loan->total_repayment*100):0 }}%"></span></div></div><div class="stat"><small>Installment</small><strong>TZS {{ number_format($loan->installment_amount) }}</strong><em>{{ $loan->number_of_installments }} installments</em></div></div>
@if($status==='pending_disbursement')<div class="card"><div class="card-head"><h2>Disburse approved loan</h2></div><form class="card-body" method="POST" action="{{ route('admin.loans.disburse',$loan) }}">@csrf<div class="form-grid"><label>Method<select name="method"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="airtel_money">Airtel Money</option><option value="mixx">Mixx</option><option value="bank_transfer">Bank transfer</option></select></label><label>Recipient number<input name="recipient_number"></label><label>Reference<input name="reference_number"></label><label>Disbursement date<input type="date" name="disbursed_at" value="{{ today()->format('Y-m-d') }}"></label><label>First payment date<input type="date" name="first_payment_date" value="{{ today()->addWeek()->format('Y-m-d') }}"></label></div><div class="form-actions"><button class="btn btn-primary" data-confirm="Disburse this loan and generate its repayment schedule?">Confirm disbursement</button></div></form></div><br>@endif
<div class="grid-2"><div><div class="card"><div class="card-head"><h2>Repayment schedule</h2><span>{{ $loan->installments->where('status','paid')->count() }} / {{ $loan->number_of_installments }} paid</span></div><div class="table-wrap"><table><thead><tr><th>#</th><th>Due date</th><th>Due</th><th>Paid</th><th>Balance</th><th>Status</th><th>Repayment</th></tr></thead><tbody>
@forelse($loan->installments as $item)
    @php($installmentBalance = max(0, (float) $item->total_due - (float) $item->total_paid - (float) $item->interest_exemption))
    <tr><td>{{ $item->installment_number }}</td><td>{{ $item->due_date->format('d M Y') }}</td><td class="money">{{ number_format($item->total_due, 2) }}</td><td class="money">{{ number_format($item->total_paid, 2) }}</td><td class="money">{{ number_format($installmentBalance, 2) }}</td><td><span class="badge {{ $item->status }}">{{ str_replace('_',' ',$item->status) }}</span></td><td>
        @if(in_array($status, ['active','overdue']) && $installmentBalance > 0)
            @can('collect-payments')
                <form method="POST" action="{{ route('admin.payments.store', $loan) }}" style="display:flex;align-items:end;gap:6px;min-width:310px">@csrf<input type="hidden" name="loan_installment_id" value="{{ $item->id }}"><label style="margin:0;min-width:115px"><small>Amount</small><input type="number" name="amount" min="0.01" max="{{ number_format($installmentBalance, 2, '.', '') }}" step="0.01" value="{{ number_format($installmentBalance, 2, '.', '') }}" required></label><label style="margin:0;min-width:100px"><small>Method</small><select name="payment_method" data-select2="false"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="airtel_money">Airtel Money</option><option value="mixx">Mixx</option><option value="bank_transfer">Bank</option></select></label><button class="btn btn-sm btn-primary" data-confirm="Confirm this installment repayment?">Confirm repayment</button></form>
            @else
                <span class="muted">No collection permission</span>
            @endcan
        @else
            <span class="muted">Completed</span>
        @endif
    </td></tr>
@empty
    <tr><td colspan="7" class="empty">Schedule will appear after disbursement.</td></tr>
@endforelse
</tbody></table></div></div><br>
<div class="card"><div class="card-head"><h2>Payment history</h2></div><div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>@forelse($loan->payments->sortByDesc('paid_at') as $payment)<tr><td>{{ $payment->payment_number }}</td><td>{{ $payment->paid_at->format('d M Y H:i') }}</td><td>{{ str_replace('_',' ',$payment->payment_method) }}</td><td class="money">TZS {{ number_format($payment->amount) }}</td><td><span class="badge {{ $payment->status }}">{{ $payment->status }}</span></td><td>@if($payment->status==='posted')<form method="POST" action="{{ route('admin.payments.reverse',$payment) }}">@csrf<input type="hidden" name="reason" value="Reversed by authorized web portal user"><button class="btn btn-sm btn-danger" data-confirm="Reverse this payment and restore balances?">Reverse</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="empty">No repayments posted.</td></tr>@endforelse</tbody></table></div></div></div>
<div>@if(in_array($status,['active','overdue']))<div class="card"><div class="card-head"><h2>Post repayment</h2></div><form class="card-body" method="POST" action="{{ route('admin.payments.store',$loan) }}">@csrf<div class="form-grid"><label>Amount (TZS)<input type="number" name="amount" min="1" max="{{ $loan->total_balance }}" required></label><label>Method<select name="payment_method"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="airtel_money">Airtel Money</option><option value="mixx">Mixx</option><option value="bank_transfer">Bank transfer</option></select></label><label>Reference<input name="reference_number"></label><label>Paid at<input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label><label class="full">Remarks<input name="remarks"></label></div><div class="form-actions"><button class="btn btn-gold">Post repayment</button></div></form></div><br>
<div class="card"><div class="card-head"><h2>Early settlement</h2></div><form class="card-body" method="POST" action="{{ route('admin.loans.settle',$loan) }}">@csrf<p class="muted">The combined cash, security offset, and interest waiver must exactly clear TZS {{ number_format($loan->total_balance) }}.</p><div class="form-grid"><label>Cash payment<input type="number" name="cash_payment" min="0" value="{{ $loan->total_balance }}"></label><label>Security offset<input type="number" name="security_offset" min="0" value="0"></label><label>Interest waived<input type="number" name="interest_waived" min="0" max="{{ $loan->interest_balance }}" value="0"></label><label>Security refund<input type="number" name="security_refund" min="0" value="0"></label></div><div class="form-actions"><button class="btn btn-danger" data-confirm="Settle and close this loan?">Settle loan</button></div></form></div>@endif
<br><div class="card"><div class="card-head"><h2>Loan details</h2></div><div class="card-body detail-grid" style="grid-template-columns:1fr 1fr"><div class="detail"><small>Disbursed</small><strong>{{ $loan->disbursement_date?->format('d M Y')??'—' }}</strong></div><div class="detail"><small>Maturity</small><strong>{{ $loan->maturity_date?->format('d M Y')??'—' }}</strong></div><div class="detail"><small>Principal balance</small><strong>TZS {{ number_format($loan->principal_balance) }}</strong></div><div class="detail"><small>Interest balance</small><strong>TZS {{ number_format($loan->interest_balance) }}</strong></div></div></div></div></div>
<br>
@if(in_array($status, ['active', 'overdue']))
@can('issue-default-notices')
<div class="card">
    <div class="card-head"><h2>Fourteen-day default notice</h2><span>{{ $loan->defaultNotices->count() }} issued</span></div>
    <form class="card-body" method="POST" action="{{ route('admin.loans.default-notices.store', $loan) }}">
        @csrf
        <div class="form-grid">
            <label>Delivery method<select name="delivery_method"><option value="hand">Hand delivery</option><option value="sms">SMS</option><option value="email">Email</option><option value="registered_mail">Registered mail</option></select></label>
            <label>Delivery reference<input name="delivery_reference"></label>
        </div>
        <div class="form-actions"><button class="btn btn-danger" data-confirm="Issue a formal 14-day default notice?">Issue notice</button></div>
    </form>
</div>
@endcan
@endif

@if($status === 'settled')
<div class="card">
    <div class="card-head"><h2>Loan-clearance authorization</h2><span>{{ $loan->clearance?->status ?? 'pending' }}</span></div>
    @if($loan->clearance?->status === 'authorized')
        <div class="card-body"><p>This member has no debts or dues left with VATI, and VATI has no loan dues outstanding to the member.</p><p class="muted">Authorized {{ $loan->clearance->authorized_at?->format('d M Y H:i') }}</p></div>
    @else
        @can('authorize-loan-clearances')
        <form class="card-body" method="POST" enctype="multipart/form-data" action="{{ route('admin.loans.clearance.store', $loan) }}">
            @csrf
            <div class="form-grid"><label>Branch-manager signature<input type="file" name="manager_signature" accept="image/*" required></label><label>Comments<input name="comments"></label></div>
            <div class="form-actions"><button class="btn btn-primary">Authorize loan clearance</button></div>
        </form>
        @endcan
    @endif
</div>
@endif
@endsection
