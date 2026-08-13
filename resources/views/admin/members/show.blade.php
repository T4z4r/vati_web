@extends('layouts.admin')
@section('title', $member->membership_number)

@section('content')
@php
    $fullName = trim(collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' '));
    $display = fn ($value, $fallback = '—') => filled($value) ? $value : $fallback;
    $money = fn ($value) => 'TZS '.number_format((float) ($value ?? 0), 2);
@endphp

<div class="page-head">
    <div>
        <p class="eyebrow">{{ $member->membership_number }}</p>
        <h1>{{ $fullName }}</h1>
        <p>Kitabu cha Marejesho ya Mteja (Member's Passbook)</p>
    </div>
    <div class="head-actions">
        <span class="badge {{ $member->status }}">{{ ucfirst($member->status) }}</span>
        @can('create-loan-applications')
            <a class="btn btn-primary" href="{{ route('admin.loan-applications.create', ['member_id' => $member->id]) }}">New loan application</a>
        @endcan
        @can('edit-members')
            <a class="btn btn-secondary" href="{{ route('admin.members.edit', $member) }}">Edit member</a>
        @endcan
        @can('delete-members')
            <form method="POST" action="{{ route('admin.members.destroy', $member) }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger" data-confirm="Delete this member? Members with loan history cannot be deleted.">Delete</button>
            </form>
        @endcan
    </div>
</div>

<div class="card" style="background:linear-gradient(135deg,#16452a,#267044);color:#fff;margin-bottom:20px">
    <div class="card-body detail-grid">
        <div><small style="opacity:.75">Membership / SL number</small><strong style="display:block;font-size:18px;margin-top:5px">{{ $member->membership_number }}</strong></div>
        <div><small style="opacity:.75">Branch / Jina la Tawi</small><strong style="display:block;margin-top:5px">{{ $display($member->branch?->branch_name) }}</strong></div>
        <div><small style="opacity:.75">Group / Jina la Kikundi</small><strong style="display:block;margin-top:5px">{{ $display($member->group?->group_name) }}</strong></div>
        <div><small style="opacity:.75">Meeting day / Siku ya Kukutana</small><strong style="display:block;margin-top:5px">{{ $display($member->group?->meeting_day) }}</strong></div>
        <div><small style="opacity:.75">Group location / Mahali Kikundi</small><strong style="display:block;margin-top:5px">{{ $display($member->group?->location) }}</strong></div>
        <div><small style="opacity:.75">Member contact / Namba ya Simu</small><strong style="display:block;margin-top:5px">{{ $display($member->phone) }}</strong></div>
    </div>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-head"><h2>Member profile / Wasifu wa Mwanachama</h2></div>
            <div class="card-body detail-grid">
                <div class="detail"><small>Full name / Jina la Mwanachama</small><strong>{{ $fullName }}</strong></div>
                <div class="detail"><small>Guardian, father or husband</small><strong>{{ $display($member->guardian_name) }}</strong></div>
                <div class="detail"><small>Primary phone</small><strong>{{ $display($member->phone) }}</strong></div>
                <div class="detail"><small>Alternate phone</small><strong>{{ $display($member->alternate_phone) }}</strong></div>
                <div class="detail"><small>National ID</small><strong>{{ $display($member->national_id) }}</strong></div>
                <div class="detail"><small>Voter ID</small><strong>{{ $display($member->voter_id) }}</strong></div>
                <div class="detail"><small>Date of birth</small><strong>{{ $member->date_of_birth?->format('d M Y') ?? '—' }}</strong></div>
                <div class="detail"><small>Gender</small><strong>{{ $display($member->gender) }}</strong></div>
                <div class="detail"><small>Marital status</small><strong>{{ $display($member->marital_status) }}</strong></div>
                <div class="detail"><small>Occupation</small><strong>{{ $display($member->occupation) }}</strong></div>
                <div class="detail"><small>Nationality</small><strong>{{ $display($member->nationality) }}</strong></div>
                <div class="detail"><small>Admission date / Tarehe ya kujiunga</small><strong>{{ $member->admission_date?->format('d M Y') ?? '—' }}</strong></div>
                <div class="detail"><small>Passbook issue date</small><strong>{{ $member->passbook_issue_date?->format('d M Y') ?? '—' }}</strong></div>
                <div class="detail"><small>Group join date</small><strong>{{ $member->activeGroupMembership?->joined_at?->format('d M Y') ?? '—' }}</strong></div>
                <div class="detail"><small>Record status</small><strong>{{ ucfirst($member->status) }}</strong></div>
            </div>
        </div>
        <br>

        <div class="card">
            <div class="card-head"><h2>Address and issuing authority</h2></div>
            <div class="card-body detail-grid">
                <div class="detail"><small>Physical address / Anuani ya makazi</small><strong>{{ $display($member->physical_address) }}</strong></div>
                <div class="detail"><small>Region / Mkoa</small><strong>{{ $display($member->region) }}</strong></div>
                <div class="detail"><small>District / Wilaya</small><strong>{{ $display($member->district) }}</strong></div>
                <div class="detail"><small>Ward / Kata</small><strong>{{ $display($member->ward) }}</strong></div>
                <div class="detail"><small>Street / Mtaa</small><strong>{{ $display($member->street) }}</strong></div>
                <div class="detail"><small>Issuing branch address</small><strong>{{ $display($member->branch?->address) }}</strong></div>
                <div class="detail"><small>Issued / registered by</small><strong>{{ $display($member->createdBy?->name) }}</strong></div>
                <div class="detail"><small>Branch manager</small><strong>{{ $display($member->branch?->manager?->name) }}</strong></div>
                <div class="detail"><small>Record created</small><strong>{{ $member->created_at?->format('d M Y H:i') ?? '—' }}</strong></div>
            </div>
        </div>
        <br>

        <div class="card">
            <div class="card-head"><h2>Documents and attachments</h2><span class="badge {{ $member->documents->isNotEmpty() ? 'active' : 'pending' }}">{{ $member->documents->count() }} files</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Document type</th><th>File</th><th>Uploaded</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($member->documents as $document)
                        <tr>
                            <td>{{ str_replace('_', ' ', ucfirst($document->document_type)) }}</td>
                            <td>{{ $document->file_name }}</td>
                            <td>{{ $document->created_at?->format('d M Y') }}</td>
                            <td>
                                <a class="btn btn-sm btn-secondary" href="{{ route('admin.members.documents.download', [$member, $document]) }}">Download</a>
                                @can('delete-members')
                                    <form method="POST" action="{{ route('admin.members.documents.destroy', [$member, $document]) }}" style="display:inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" data-confirm="Delete this document?">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No documents uploaded.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @can('edit-members')
                <form class="card-body" method="POST" action="{{ route('admin.members.documents.store', $member) }}" enctype="multipart/form-data" style="border-top:1px solid var(--line)">
                    @csrf
                    <div class="form-grid">
                        <label>Document type<select name="document_type" required><option value="">Select type</option><option value="national_id">National ID</option><option value="voter_id">Voter ID</option><option value="address_proof">Proof of address</option><option value="business_license">Business license</option><option value="passbook_scan">Passbook scan</option><option value="signature_card">Signature card</option><option value="other">Other</option></select></label>
                        <label>File<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></label>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">Upload document</button></div>
                </form>
            @endcan
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-head"><h2>KYC, business and household</h2><span class="badge {{ $member->kyc ? 'active' : 'pending' }}">{{ $member->kyc ? 'Captured' : 'Incomplete' }}</span></div>
            @can('edit-members')
            <form class="card-body" method="POST" action="{{ route('admin.members.kyc.update', $member) }}">
                @csrf @method('PUT')
                <div class="form-grid">
                    <label>Business name<input name="business_name" value="{{ $member->kyc?->business_name }}"></label>
                    <label>Business type<input name="business_type" value="{{ $member->kyc?->business_type }}"></label>
                    <label class="full">Business address<textarea name="business_address">{{ $member->kyc?->business_address }}</textarea></label>
                    <label>M-Pesa phone<input name="mpesa_phone" value="{{ $member->kyc?->mpesa_phone }}"></label>
                    <label>Bank account number<input name="bank_account_number" value="{{ $member->kyc?->bank_account_number }}"></label>
                    <label>Bank account name<input name="bank_account_name" value="{{ $member->kyc?->bank_account_name }}"></label>
                    <label>Bank name<input name="bank_name" value="{{ $member->kyc?->bank_name }}"></label>
                    <label>House number<input name="house_number" value="{{ $member->kyc?->house_number }}"></label>
                    <label>Nearest police station<input name="police_station" value="{{ $member->kyc?->police_station }}"></label>
                    <label>Monthly household income<input type="number" step="0.01" name="household_monthly_income" value="{{ $member->kyc?->household_monthly_income }}"></label>
                    <label>Monthly household expenses<input type="number" step="0.01" name="household_monthly_expenses" value="{{ $member->kyc?->household_monthly_expenses }}"></label>
                    <label>Number of dependants<input type="number" name="number_of_dependants" value="{{ $member->kyc?->number_of_dependants }}"></label>
                    <label>Head of household<input name="head_of_household" value="{{ $member->kyc?->head_of_household }}"></label>
                    <label>House ownership<input name="house_ownership_status" value="{{ $member->kyc?->house_ownership_status }}"></label>
                    <label>Roof type<input value="{{ $member->kyc?->house_roof_type }}" disabled></label>
                    <label>Fence type<input value="{{ $member->kyc?->house_fence_type }}" disabled></label>
                </div>
                <div class="form-actions"><button class="btn btn-primary">Save KYC</button></div>
            </form>
            @else
            <div class="card-body detail-grid" style="grid-template-columns:1fr 1fr">
                <div class="detail"><small>Business name</small><strong>{{ $display($member->kyc?->business_name) }}</strong></div>
                <div class="detail"><small>Business type</small><strong>{{ $display($member->kyc?->business_type) }}</strong></div>
                <div class="detail"><small>Business address</small><strong>{{ $display($member->kyc?->business_address) }}</strong></div>
                <div class="detail"><small>M-Pesa phone</small><strong>{{ $display($member->kyc?->mpesa_phone) }}</strong></div>
                <div class="detail"><small>Bank account number</small><strong>{{ $display($member->kyc?->bank_account_number) }}</strong></div>
                <div class="detail"><small>Bank account name</small><strong>{{ $display($member->kyc?->bank_account_name) }}</strong></div>
                <div class="detail"><small>Bank name</small><strong>{{ $display($member->kyc?->bank_name) }}</strong></div>
                <div class="detail"><small>House number</small><strong>{{ $display($member->kyc?->house_number) }}</strong></div>
                <div class="detail"><small>Nearest police station</small><strong>{{ $display($member->kyc?->police_station) }}</strong></div>
                <div class="detail"><small>Monthly income</small><strong>{{ $money($member->kyc?->household_monthly_income) }}</strong></div>
                <div class="detail"><small>Monthly expenses</small><strong>{{ $money($member->kyc?->household_monthly_expenses) }}</strong></div>
                <div class="detail"><small>Number of dependants</small><strong>{{ $member->kyc?->number_of_dependants ?? 0 }}</strong></div>
                <div class="detail"><small>Head of household</small><strong>{{ $display($member->kyc?->head_of_household) }}</strong></div>
                <div class="detail"><small>House ownership</small><strong>{{ $display($member->kyc?->house_ownership_status) }}</strong></div>
                <div class="detail"><small>Roof type</small><strong>{{ $display($member->kyc?->house_roof_type) }}</strong></div>
                <div class="detail"><small>Fence type</small><strong>{{ $display($member->kyc?->house_fence_type) }}</strong></div>
            </div>
            @endcan
        </div>
        <br>

        <div class="card">
            <div class="card-head"><h2>Nominees</h2><span>{{ $member->nominees->count() }}</span></div>
            <div class="table-wrap"><table><thead><tr><th>Name</th><th>Relationship</th><th>Share</th><th>Attested</th></tr></thead><tbody>
                @forelse($member->nominees as $nominee)
                    <tr><td>{{ $nominee->name }}</td><td>{{ $display($nominee->relationship) }}</td><td>{{ number_format((float) $nominee->percentage, 2) }}%</td><td>{{ $nominee->attested_at?->format('d M Y') ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty">No nominees recorded.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <br>

        <div class="card">
            <div class="card-head"><h2>Security account / Akaunti ya Usalama</h2><strong class="money">{{ $money($member->securityAccount?->balance) }}</strong></div>
            @can('manage-security')
            <form class="card-body" method="POST" action="{{ route('admin.security.store', $member) }}">
                @csrf
                <div class="form-grid">
                    <label>Transaction type<select name="transaction_type"><option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option><option value="refund">Refund</option><option value="adjustment">Adjustment</option></select></label>
                    <label>Amount<input type="number" step="0.01" name="amount" min="1" required></label>
                    <label class="full">Remarks<input name="remarks"></label>
                </div>
                <div class="form-actions"><button class="btn btn-gold">Post transaction</button></div>
            </form>
            @endcan
        </div>
        <br>

        @can('replace-passbooks')
        <div class="card">
            <div class="card-head"><h2>Duplicate passbook</h2><span>TZS 1,000</span></div>
            <form class="card-body" method="POST" action="{{ route('admin.members.passbook-replacements.store', $member) }}">
                @csrf
                <div class="form-grid"><label>Reason<select name="reason"><option value="lost">Lost</option><option value="damaged">Damaged</option></select></label><label>Payment reference<input name="payment_reference" required></label><label class="full">Remarks<input name="remarks"></label></div>
                <div class="form-actions"><button class="btn btn-gold">Record and issue duplicate</button></div>
            </form>
        </div>
        @endcan
    </div>
</div>

<br>
<div class="card">
    <div class="card-head"><h2>Loan applications</h2><span>{{ $applications->count() }} applications</span></div>
    <div class="table-wrap"><table><thead><tr><th>Application</th><th>Type</th><th>Product</th><th>Requested</th><th>Duration</th><th>Purpose</th><th>Status</th></tr></thead><tbody>
        @forelse($applications as $application)
            <tr>
                <td>@can('view-loan-applications')<a class="table-link" href="{{ route('admin.loan-applications.show', $application) }}">{{ $application->application_number }}</a>@else{{ $application->application_number }}@endcan</td>
                <td>{{ ucfirst($application->application_type) }}</td>
                <td>{{ $display($application->product?->name) }}</td>
                <td class="money">{{ $money($application->requested_amount) }}</td>
                <td>{{ $application->duration_months }} months</td>
                <td>{{ $display($application->loan_purpose) }}</td>
                <td><span class="badge {{ $application->status->value }}">{{ str_replace('_', ' ', $application->status->value) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No loan applications yet.</td></tr>
        @endforelse
    </tbody></table></div>
</div>

<br>
<div class="card">
    <div class="card-head"><h2>Complete loan history / Historia ya Mikopo</h2><span>{{ $loans->count() }} loans</span></div>
</div>

@forelse($loans as $loan)
    @php
        $loanStatus = $loan->status->value;
        $paidAmount = max(0, (float) $loan->total_repayment - (float) $loan->total_balance);
        $progress = (float) $loan->total_repayment > 0 ? min(100, max(0, $paidAmount / (float) $loan->total_repayment * 100)) : 0;
    @endphp
    <div class="card" style="margin-top:20px;border-top:4px solid var(--green)">
        <div class="card-head">
            <div><h2>@can('view-loans')<a class="table-link" href="{{ route('admin.loans.show', $loan) }}">{{ $loan->loan_number }}</a>@else{{ $loan->loan_number }}@endcan</h2><small>{{ $display($loan->product?->name) }} · {{ ucfirst($loan->loan_cycle ?? $loan->application?->application_type ?? 'main') }} loan cycle</small></div>
            <span class="badge {{ $loanStatus }}">{{ str_replace('_', ' ', $loanStatus) }}</span>
        </div>
        <div class="card-body">
            <div class="stats" style="margin-bottom:20px">
                <div class="stat gold"><small>Principal</small><strong>{{ $money($loan->principal_amount) }}</strong></div>
                <div class="stat"><small>Total repayment</small><strong>{{ $money($loan->total_repayment) }}</strong></div>
                <div class="stat"><small>Amount paid</small><strong>{{ $money($paidAmount) }}</strong></div>
                <div class="stat"><small>Outstanding balance</small><strong>{{ $money($loan->total_balance) }}</strong><div class="progress"><span style="width:{{ $progress }}%"></span></div></div>
            </div>

            <h3 class="section-title">Loan information / Taarifa za Mkopo</h3>
            <div class="detail-grid" style="grid-template-columns:repeat(4,1fr)">
                <div class="detail"><small>Project / business</small><strong>{{ $display($loan->business_name ?? $loan->application?->business_summary) }}</strong></div>
                <div class="detail"><small>Loan purpose</small><strong>{{ $display($loan->application?->loan_purpose) }}</strong></div>
                <div class="detail"><small>Interest rate</small><strong>{{ filled($loan->interest_rate) ? number_format((float) $loan->interest_rate, 2).'%' : '—' }}</strong></div>
                <div class="detail"><small>Disbursement date</small><strong>{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</strong></div>
                <div class="detail"><small>First payment date</small><strong>{{ $loan->first_payment_date?->format('d M Y') ?? '—' }}</strong></div>
                <div class="detail"><small>Maturity date</small><strong>{{ $loan->maturity_date?->format('d M Y') ?? '—' }}</strong></div>
                <div class="detail"><small>Adjusted principal</small><strong>{{ $money($loan->adjusted_principal_amount ?? $loan->principal_amount) }}</strong></div>
                <div class="detail"><small>Main loan with interest</small><strong>{{ $money($loan->total_repayment) }}</strong></div>
                <div class="detail"><small>Admission fee</small><strong>{{ $money($loan->admission_fee) }}</strong></div>
                <div class="detail"><small>Processing fee</small><strong>{{ $money($loan->processing_fee) }}</strong></div>
                <div class="detail"><small>Transaction charges</small><strong>{{ $money($loan->transaction_charges) }}</strong></div>
                <div class="detail"><small>Other charges</small><strong>{{ $money($loan->other_charges) }}</strong></div>
                <div class="detail"><small>Total fees and VAT</small><strong>{{ $money($loan->total_fees_and_vat) }}</strong></div>
                <div class="detail"><small>Increment amount</small><strong>{{ $money($loan->increment_amount) }}</strong></div>
                <div class="detail"><small>Refinancing amount</small><strong>{{ $money($loan->refinancing_amount) }}</strong></div>
                <div class="detail"><small>Weekly installment</small><strong>{{ $money($loan->weekly_installment ?: $loan->installment_amount) }}</strong></div>
                <div class="detail"><small>Total installments</small><strong>{{ $loan->number_of_installments }}</strong></div>
                <div class="detail"><small>Principal balance</small><strong>{{ $money($loan->principal_balance) }}</strong></div>
                <div class="detail"><small>Interest balance</small><strong>{{ $money($loan->interest_balance) }}</strong></div>
                <div class="detail"><small>Applicant signature</small><strong>{{ $loan->application?->applicant_signature_path ? 'Captured' : '—' }}</strong></div>
                <div class="detail"><small>Applicant thumbprint</small><strong>{{ $loan->application?->applicant_thumbprint_path ? 'Captured' : '—' }}</strong></div>
            </div>
        </div>

        @if($loan->cycles->isNotEmpty())
        <div class="card-head"><h3>Loan cycles / Awamu za Mkopo</h3></div>
        @foreach($loan->cycles as $cycle)
            <div class="card-body" style="border-bottom:1px solid var(--line)">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:15px"><strong>{{ ucfirst($cycle->cycle_type) }} · {{ $display($cycle->business_name, 'Unnamed project') }}</strong><span class="badge {{ $cycle->status }}">{{ $cycle->status }}</span></div>
                <div class="detail-grid" style="grid-template-columns:repeat(4,1fr)">
                    <div class="detail"><small>Principal</small><strong>{{ $money($cycle->principal_amount) }}</strong></div>
                    <div class="detail"><small>Adjusted principal</small><strong>{{ $money($cycle->adjusted_principal_amount) }}</strong></div>
                    <div class="detail"><small>Interest rate</small><strong>{{ number_format((float) $cycle->interest_rate, 2) }}%</strong></div>
                    <div class="detail"><small>Loan with interest</small><strong>{{ $money($cycle->total_with_interest) }}</strong></div>
                    <div class="detail"><small>Disbursement date</small><strong>{{ $cycle->disbursement_date?->format('d M Y') ?? '—' }}</strong></div>
                    <div class="detail"><small>First payment date</small><strong>{{ $cycle->first_payment_date?->format('d M Y') ?? '—' }}</strong></div>
                    <div class="detail"><small>Admission fee</small><strong>{{ $money($cycle->admission_fee) }}</strong></div>
                    <div class="detail"><small>Processing fee</small><strong>{{ $money($cycle->processing_fee) }}</strong></div>
                    <div class="detail"><small>Transaction charges</small><strong>{{ $money($cycle->transaction_charges) }}</strong></div>
                    <div class="detail"><small>Other charges</small><strong>{{ $money($cycle->other_charges) }}</strong></div>
                    <div class="detail"><small>VAT</small><strong>{{ $money($cycle->vat_amount) }}</strong></div>
                    <div class="detail"><small>Total fees and VAT</small><strong>{{ $money($cycle->total_fees_and_vat) }}</strong></div>
                    <div class="detail"><small>Increment amount</small><strong>{{ $money($cycle->increment_amount) }}</strong></div>
                    <div class="detail"><small>Refinancing amount</small><strong>{{ $money($cycle->refinancing_amount) }}</strong></div>
                    <div class="detail"><small>Weekly installment</small><strong>{{ $money($cycle->weekly_installment) }}</strong></div>
                    <div class="detail"><small>Total installments</small><strong>{{ $cycle->total_installments }}</strong></div>
                    <div class="detail"><small>Notes</small><strong>{{ $display($cycle->notes) }}</strong></div>
                </div>
            </div>
        @endforeach
        @endif

        <div class="card-head"><h3>Loan security / Kiasi cha Dhamana</h3><strong>{{ $money($loan->securityTransactions->sortByDesc('transaction_date')->first()?->balance) }}</strong></div>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Security amount</th><th>Withdrawal</th><th>Balance</th><th>Collector</th><th>Approved by</th></tr></thead><tbody>
            @forelse($loan->securityTransactions->sortByDesc('transaction_date') as $transaction)
                <tr><td>{{ $transaction->transaction_date?->format('d M Y') }}</td><td class="money">{{ $money($transaction->security_amount) }}</td><td class="money">{{ $money($transaction->withdrawal_amount) }}</td><td class="money">{{ $money($transaction->balance) }}</td><td>{{ $display($transaction->collectedBy?->name) }}</td><td>{{ $display($transaction->approvedBy?->name) }}</td></tr>
            @empty
                <tr><td colspan="6" class="empty">No loan-security transactions recorded.</td></tr>
            @endforelse
        </tbody></table></div>

        <div class="card-head"><h3>Installment collection / Taarifa za Marejesho</h3></div>
        <div class="table-wrap"><table><thead><tr><th>#</th><th>Date</th><th>Principal</th><th>Interest</th><th>Total due</th><th>Paid</th><th>Interest exemption</th><th>Outstanding</th><th>Remarks / Collector</th><th>Status</th></tr></thead><tbody>
            @if($loan->installmentRecords->isNotEmpty())
                @foreach($loan->installmentRecords->sortBy('installment_number') as $installment)
                    <tr><td>{{ $installment->installment_number }}</td><td>{{ $installment->payment_date?->format('d M Y') }}</td><td class="money">{{ $money($installment->principal_amount) }}</td><td class="money">{{ $money($installment->interest_amount) }}</td><td class="money">{{ $money($installment->total_amount) }}</td><td class="money">{{ $installment->is_paid ? $money($installment->total_amount) : $money(0) }}</td><td class="money">{{ $money($installment->interest_exemption) }}</td><td class="money">{{ $money($installment->outstanding_balance) }}</td><td>{{ $display($installment->remarks ?? $installment->collector?->name) }}</td><td><span class="badge {{ $installment->status_badge }}">{{ $installment->status_badge }}</span></td></tr>
                @endforeach
            @else
                @forelse($loan->installments->sortBy('installment_number') as $installment)
                    <tr><td>{{ $installment->installment_number }}</td><td>{{ $installment->due_date?->format('d M Y') }}</td><td class="money">{{ $money($installment->principal_due) }}</td><td class="money">{{ $money($installment->interest_due) }}</td><td class="money">{{ $money($installment->total_due) }}</td><td class="money">{{ $money($installment->total_paid) }}</td><td class="money">{{ $money($installment->interest_exemption) }}</td><td class="money">{{ $money($installment->outstanding_balance) }}</td><td>—</td><td><span class="badge {{ $installment->status }}">{{ str_replace('_', ' ', $installment->status) }}</span></td></tr>
                @empty
                    <tr><td colspan="10" class="empty">Repayment schedule has not been generated.</td></tr>
                @endforelse
            @endif
        </tbody></table></div>

        <div class="card-head"><h3>Payments received</h3><span>{{ $loan->payments->count() }}</span></div>
        <div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead><tbody>
            @forelse($loan->payments->sortByDesc('paid_at') as $payment)
                <tr><td>{{ $payment->payment_number }}</td><td>{{ $payment->paid_at?->format('d M Y H:i') }}</td><td>{{ str_replace('_', ' ', $payment->payment_method) }}</td><td>{{ $display($payment->reference_number) }}</td><td class="money">{{ $money($payment->amount) }}</td><td><span class="badge {{ $payment->status }}">{{ $payment->status }}</span></td></tr>
            @empty
                <tr><td colspan="6" class="empty">No payments received.</td></tr>
            @endforelse
        </tbody></table></div>

        @if($loan->application?->guarantors?->isNotEmpty())
        <div class="card-head"><h3>Guarantors / Wadhamini</h3></div>
        <div class="table-wrap"><table><thead><tr><th>Name</th><th>Type</th><th>Relationship</th><th>Phone</th><th>National ID</th><th>Address</th><th>Signature</th><th>Thumbprint</th><th>Joint photo</th></tr></thead><tbody>
            @foreach($loan->application->guarantors as $guarantor)
                <tr><td>{{ $guarantor->name }}</td><td>{{ $display($guarantor->guarantor_type) }}</td><td>{{ $display($guarantor->relationship) }}</td><td>{{ $display($guarantor->phone) }}</td><td>{{ $display($guarantor->national_id) }}</td><td>{{ $display(collect([$guarantor->street, $guarantor->ward, $guarantor->district, $guarantor->region])->filter()->implode(', ')) }}</td><td>{{ $guarantor->signature_path ? 'Captured' : '—' }}</td><td>{{ $guarantor->thumbprint_path ? 'Captured' : '—' }}</td><td>{{ $guarantor->joint_photo_path ? 'Captured' : '—' }}</td></tr>
            @endforeach
        </tbody></table></div>
        @endif

        @if($loan->settlement || $loan->clearance)
        <div class="card-head"><h3>Adjustment and loan clearance</h3></div>
        <div class="card-body detail-grid" style="grid-template-columns:repeat(4,1fr)">
            <div class="detail"><small>Loan outstanding at clearance</small><strong>{{ $money($loan->clearance?->loan_outstanding_amount) }}</strong></div>
            <div class="detail"><small>Security deduction</small><strong>{{ $money($loan->clearance?->security_offset) }}</strong></div>
            <div class="detail"><small>Cash collection</small><strong>{{ $money($loan->clearance?->cash_collection) }}</strong></div>
            <div class="detail"><small>Security return</small><strong>{{ $money($loan->clearance?->security_refund) }}</strong></div>
            <div class="detail"><small>Clearance status</small><strong>{{ $display($loan->clearance?->status) }}</strong></div>
            <div class="detail"><small>Authorized date</small><strong>{{ $loan->clearance?->authorized_at?->format('d M Y H:i') ?? '—' }}</strong></div>
            <div class="detail"><small>Comments</small><strong>{{ $display($loan->clearance?->comments) }}</strong></div>
        </div>
        @endif
    </div>
@empty
    <div class="card" style="margin-top:20px"><div class="card-body empty">No loans found for this member.</div></div>
@endforelse

<style>
    @media(max-width:1100px){.card .detail-grid[style*="repeat(4"]{grid-template-columns:repeat(2,1fr)!important}}
    @media(max-width:760px){.card .detail-grid[style*="repeat(4"]{grid-template-columns:1fr!important}}
</style>
@endsection
