<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Loan Details {{ $loan->loan_number }}</title>
    <style>
        @page { margin: 22px 28px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: DejaVu Sans, sans-serif; font-size: 8.4px; line-height: 1.28; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: .7px solid #555; padding: 3px 4px; vertical-align: top; }
        th { background: #f2f2f2; font-weight: 700; text-align: left; }
        .no-border td { border: 0; }
        .header-logo { width: 300px; height: auto; }
        .company-address { margin-top: 3px; font-size: 9px; text-align: center; }
        .title { margin: 8px 0; padding: 5px; color: #fff; background: #111; font-size: 12px; font-weight: 700; text-align: center; }
        .section { margin: 7px 0 3px; padding: 4px; color: #fff; background: #111; font-size: 9px; font-weight: 700; text-align: center; text-transform: uppercase; }
        .label { font-weight: 700; }
        .money { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .right { text-align: right; }
        .muted { color: #666; }
        .page-break { page-break-after: always; }
        .avoid-break { page-break-inside: avoid; }
        .stat-grid { display: flex; gap: 0; }
        .stat-box { flex: 1; border: .7px solid #555; padding: 6px 8px; text-align: center; }
        .stat-box small { display: block; color: #666; font-size: 7.5px; }
        .stat-box strong { display: block; font-size: 11px; margin-top: 2px; }
        .stat-box em { display: block; font-size: 7.5px; color: #666; margin-top: 1px; font-style: normal; }
        .footer { position: fixed; right: 0; bottom: -20px; left: 0; color: #666; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
@php
    $blank = '—';
    $show = fn ($value, $fallback = 'Not recorded') => filled($value) ? $value : $fallback;
    $money = fn ($value) => number_format((float) ($value ?? 0), 2);
    $moneySigned = fn ($value) => 'TZS '.$money($value);
    $date = fn ($value) => filled($value) ? \Carbon\Carbon::parse($value)->format('d/m/Y') : $blank;
    $dateTime = fn ($value) => filled($value) ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i') : $blank;
    $logo = base64_encode(file_get_contents(public_path('images/vati_wordmark.png')));
    $status = $loan->status->value;
    $paidInstallments = $loan->installments->where('status', 'paid')->count();
    $repaymentProgress = $loan->total_repayment > 0
        ? max(0, 100 - ($loan->total_balance / $loan->total_repayment) * 100)
        : 0;
@endphp

<div class="footer">VATI Microfinance Limited · Loan Details · {{ $loan->loan_number }}</div>

<table class="no-border">
    <tr>
        <td class="center"><img class="header-logo" src="data:image/png;base64,{{ $logo }}" alt="VATI Microfinance Limited"><div class="company-address">P.O. Box 4859, Dar es Salaam, Tanzania</div></td>
    </tr>
</table>

<div class="title">TAARIFA ZA MKOPO / LOAN DETAILS</div>

<table>
    <tr>
        <td class="label">Namba ya Mkopo / Loan Number</td><td>{{ $loan->loan_number }}</td>
        <td class="label">Hali / Status</td><td>{{ strtoupper(str_replace('_', ' ', $status)) }}</td>
    </tr>
    <tr>
        <td class="label">Jina la Mwanachama / Member</td><td>{{ $loan->member?->first_name }} {{ $loan->member?->last_name }}</td>
        <td class="label">Namba ya Mwanachama / Membership No.</td><td>{{ $show($loan->member?->membership_number) }}</td>
    </tr>
    <tr>
        <td class="label">Bidhaa ya Mkopo / Product</td><td>{{ $show($loan->product?->name) }}</td>
        <td class="label">Kikundi / Group</td><td>{{ $show($loan->group?->group_name) }}</td>
    </tr>
    <tr>
        <td class="label">Awamu ya Mkopo / Loan Cycle</td><td>{{ $show($loan->loan_cycle, 'Main loan cycle') }}</td>
        <td class="label">Jina la Biashara / Business</td><td>{{ $show($loan->business_name ?: $loan->application?->business_summary) }}</td>
    </tr>
</table>

<div class="section">Muhtasari wa mkopo / Loan summary</div>
<table>
    <tr>
        <td class="label">Kiwango cha riba / Interest rate</td><td>{{ number_format((float) ($loan->interest_rate ?: $loan->product?->annual_interest_rate), 2) }}%</td>
        <td class="label">Tarehe ya utoaji / Disbursement date</td><td>{{ $date($loan->disbursement_date) }}</td>
    </tr>
    <tr>
        <td class="label">Tarehe ya kwanza kulipa / First payment</td><td>{{ $date($loan->first_payment_date) }}</td>
        <td class="label">Tarehe ya mwisho / Maturity date</td><td>{{ $date($loan->maturity_date) }}</td>
    </tr>
</table>

<table>
    <tr><td class="label">Kiasi cha mkopo / Principal amount</td><td class="money">{{ $moneySigned($loan->principal_amount) }}</td><td class="label">Kiasi kilichorekebishwa / Adjusted</td><td class="money">{{ $moneySigned($loan->adjusted_principal_amount) }}</td></tr>
    <tr><td class="label">Kiasi cha riba / Interest amount</td><td class="money">{{ $moneySigned($loan->interest_amount) }}</td><td class="label">Jumla ya marejesho / Total repayment</td><td class="money">{{ $moneySigned($loan->total_repayment) }}</td></tr>
    <tr><td class="label">Salio la mtaji / Principal balance</td><td class="money">{{ $moneySigned($loan->principal_balance) }}</td><td class="label">Salio la riba / Interest balance</td><td class="money">{{ $moneySigned($loan->interest_balance) }}</td></tr>
    <tr><td class="label">Salio jumla / Total outstanding</td><td class="money"><strong>{{ $moneySigned($loan->total_balance) }}</strong></td><td class="label">Maendeleo / Progress</td><td class="money">{{ number_format($repaymentProgress, 1) }}%</td></tr>
</table>

<table>
    <tr><td class="label">Idadi ya marejesho / Installments</td><td>{{ $loan->number_of_installments }}</td><td class="label">Kiasi kwa rejesho / Installment amount</td><td class="money">{{ $moneySigned($loan->installment_amount) }}</td></tr>
    <tr><td class="label">Rejesho la wiki / Weekly installment</td><td class="money">{{ $moneySigned($loan->weekly_installment) }}</td><td class="label">Marejesho yaliyolipwa / Paid</td><td>{{ $paidInstallments }} / {{ $loan->number_of_installments }}</td></tr>
</table>

<div class="section">Gharama na ada / Fees and charges</div>
<table>
    <tr><td class="label">Ada ya uanachama / Admission fee</td><td class="money">{{ $moneySigned($loan->admission_fee) }}</td><td class="label">Ada ya usindikaji / Processing fee</td><td class="money">{{ $moneySigned($loan->processing_fee) }}</td></tr>
    <tr><td class="label">Gharama za miamala / Transaction charges</td><td class="money">{{ $moneySigned($loan->transaction_charges) }}</td><td class="label">Ada nyingine / Other charges</td><td class="money">{{ $moneySigned($loan->other_charges) }}</td></tr>
    <tr><td class="label">Jumla ya gharama na VAT / Total fees & VAT</td><td class="money">{{ $moneySigned($loan->total_fees_and_vat) }}</td><td class="label">Mkopo uliorekebishwa / Refinancing</td><td class="money">{{ $moneySigned($loan->refinancing_amount) }}</td></tr>
    <tr><td class="label">Nyongeza / Increment</td><td class="money">{{ $moneySigned($loan->increment_amount) }}</td><td></td><td></td></tr>
</table>

<div class="page-break"></div>

<div class="section">Ratiba ya marejesho / Repayment schedule</div>
<table>
    <thead>
        <tr><th>Na.</th><th>Tarehe ya kulipa<br>Due date</th><th>Kiasi kinachodaiwa<br>Due</th><th>Kiasi kilicholipwa<br>Paid</th><th>Salio<br>Balance</th><th>Hali<br>Status</th></tr>
    </thead>
    <tbody>
    @forelse($loan->installments->sortBy('installment_number') as $item)
        @php($balance = max(0, (float) $item->total_due - (float) $item->total_paid - (float) $item->interest_exemption))
        <tr>
            <td class="center">{{ $item->installment_number }}</td>
            <td>{{ $date($item->due_date) }}</td>
            <td class="money">{{ $money($item->total_due) }}</td>
            <td class="money">{{ $money($item->total_paid) }}</td>
            <td class="money">{{ $money($balance) }}</td>
            <td>{{ strtoupper(str_replace('_', ' ', $item->status)) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="center muted">Schedule will appear after disbursement.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="page-break"></div>

<div class="section">Historia ya malipo / Payment history</div>
<table>
    <thead>
        <tr><th>Resiti<br>Receipt</th><th>Tarehe<br>Date</th><th>Njia<br>Method</th><th>Kiasi<br>Amount</th><th>Hali<br>Status</th><th>Maelezo<br>Remarks</th></tr>
    </thead>
    <tbody>
    @forelse($loan->payments->sortByDesc('paid_at') as $payment)
        <tr>
            <td>{{ $payment->payment_number }}</td>
            <td>{{ $dateTime($payment->paid_at) }}</td>
            <td>{{ strtoupper(str_replace('_', ' ', $payment->payment_method)) }}</td>
            <td class="money">{{ $moneySigned($payment->amount) }}</td>
            <td>{{ strtoupper($payment->status) }}</td>
            <td>{{ $show($payment->remarks, $blank) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="center muted">No repayments posted.</td></tr>
    @endforelse
    </tbody>
</table>

@if($loan->disbursement)
<div class="section">Utoaji wa mkopo / Disbursement details</div>
<table>
    <tr><td class="label">Njia ya utoaji / Method</td><td>{{ strtoupper(str_replace('_', ' ', $loan->disbursement->method)) }}</td><td class="label">Nambari ya mpokeaji / Recipient</td><td>{{ $show($loan->disbursement->recipient_number) }}</td></tr>
    <tr><td class="label">Nambari ya marejeo / Reference</td><td>{{ $show($loan->disbursement->reference_number) }}</td><td class="label">Tarehe ya utoaji / Disbursed at</td><td>{{ $dateTime($loan->disbursement->disbursed_at) }}</td></tr>
</table>
@endif

@if($loan->settlement)
<div class="section">Ufungaji wa mkopo / Settlement details</div>
<table>
    <tr><td class="label">Malipo ya fedha / Cash payment</td><td class="money">{{ $moneySigned($loan->settlement->cash_payment) }}</td><td class="label">Kiasi kilichofutwa / Security offset</td><td class="money">{{ $moneySigned($loan->settlement->security_offset) }}</td></tr>
    <tr><td class="label">Riba iliyofutwa / Interest waived</td><td class="money">{{ $moneySigned($loan->settlement->interest_waived) }}</td><td class="label">Rudisha dhamana / Security refund</td><td class="money">{{ $moneySigned($loan->settlement->security_refund) }}</td></tr>
    <tr><td class="label">Tarehe ya ufungaji / Settlement date</td><td>{{ $date($loan->settlement->settlement_date) }}</td><td class="label">Imethibitishwa / Approved at</td><td>{{ $dateTime($loan->settlement->approved_at) }}</td></tr>
</table>
@endif

@if($loan->clearance)
<div class="section">Uthibitisho wa kufunga mkopo / Loan clearance authorization</div>
<table>
    <tr><td class="label">Hali / Status</td><td>{{ strtoupper($loan->clearance->status) }}</td><td class="label">Imethibitishwa / Authorized at</td><td>{{ $dateTime($loan->clearance->authorized_at) }}</td></tr>
    <tr><td class="label">Maoni / Comments</td><td colspan="3">{{ $show($loan->clearance->comments) }}</td></tr>
</table>
@endif

@if($loan->defaultNotices->isNotEmpty())
<div class="section">Taarifa za hatari / Default notices</div>
<table>
    <thead><tr><th>Na.</th><th>Njia ya utoaji<br>Delivery method</th><th>Nambari ya utoaji<br>Reference</th><th>Tarehe ya kutolewa<br>Issued at</th><th>Mwisho<br>Expires at</th><th>Imekubaliwa<br>Acknowledged</th></tr></thead>
    <tbody>
    @foreach($loan->defaultNotices as $notice)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ strtoupper(str_replace('_', ' ', $notice->delivery_method)) }}</td>
            <td>{{ $show($notice->delivery_reference) }}</td>
            <td>{{ $dateTime($notice->issued_at) }}</td>
            <td>{{ $dateTime($notice->expires_at) }}</td>
            <td>{{ $dateTime($notice->acknowledged_at) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if($loan->application?->guarantors?->isNotEmpty())
<div class="page-break"></div>
<div class="section">Wadhamini / Guarantors</div>
<table>
    <thead><tr><th>Na.</th><th>Jina / Name</th><th>Uhusiano<br>Relationship</th><th>Simu<br>Phone</th><th>Kitambulisho<br>ID</th></tr></thead>
    <tbody>
    @foreach($loan->application->guarantors as $guarantor)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $guarantor->name }}</td>
            <td>{{ $show($guarantor->relationship) }}</td>
            <td>{{ $show($guarantor->phone) }}</td>
            <td>{{ $show($guarantor->national_id ?: $guarantor->voter_id) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<p class="center" style="margin-top:24px"><strong>VATI MICROFINANCE LIMITED</strong><br>Mkopo: {{ $loan->loan_number }} · Mwanachama: {{ $loan->member?->first_name }} {{ $loan->member?->last_name }} · {{ $loan->member?->membership_number }}</p>
</body>
</html>
