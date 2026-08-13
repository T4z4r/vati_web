<div class="card">
    <div class="card-head"><h2>Loan application identification</h2><span>LAF {{ $application->application_number }}</span></div>
    <div class="card-body detail-grid">
        <div class="detail"><small>Master roll / membership number</small><strong>{{ $member->membership_number }}</strong></div>
        <div class="detail"><small>Branch / Tawi</small><strong>{{ $display($application->branch?->branch_name) }}</strong></div>
        <div class="detail"><small>Area / Eneo</small><strong>{{ $display($application->branch?->area?->name) }}</strong></div>
        <div class="detail"><small>Region / Mkoa</small><strong>{{ $display($application->branch?->area?->region?->name) }}</strong></div>
        <div class="detail"><small>Group / Kikundi</small><strong>{{ $display($application->group?->group_name) }}</strong></div>
        <div class="detail"><small>Application type</small><strong>{{ str($application->application_type)->replace('_', ' ')->title() }}</strong></div>
        <div class="detail"><small>Application date</small><strong>{{ $application->created_at?->format('d M Y') }}</strong></div>
        <div class="detail"><small>Submitted date</small><strong>{{ $application->submitted_at?->format('d M Y H:i') ?? 'Not submitted' }}</strong></div>
        <div class="detail"><small>Loan disbursement date</small><strong>{{ $application->loan?->disbursement_date?->format('d M Y') ?? 'Not disbursed' }}</strong></div>
    </div>
</div>

<br>
<div class="card">
    <div class="card-head"><h2>Applicant personal profile / Taarifa binafsi</h2><span>Auto-populated from member profile</span></div>
    <div class="card-body detail-grid">
        <div class="detail"><small>Applicant name</small><strong>{{ $fullName }}</strong></div>
        <div class="detail"><small>Father / husband / legal guardian</small><strong>{{ $display($member->guardian_name) }}</strong></div>
        <div class="detail"><small>Occupation</small><strong>{{ $display($member->occupation) }}</strong></div>
        <div class="detail"><small>Age</small><strong>{{ $member->date_of_birth?->age ?? 'Not recorded' }}</strong></div>
        <div class="detail"><small>Date of birth</small><strong>{{ $member->date_of_birth?->format('d M Y') ?? 'Not recorded' }}</strong></div>
        <div class="detail"><small>Gender</small><strong>{{ $display($member->gender) }}</strong></div>
        <div class="detail"><small>Religion</small><strong>Not recorded in member profile</strong></div>
        <div class="detail"><small>Phone</small><strong>{{ $display($member->phone) }}</strong></div>
        <div class="detail"><small>Alternate phone</small><strong>{{ $display($member->alternate_phone) }}</strong></div>
        <div class="detail"><small>Nationality</small><strong>{{ $display($member->nationality) }}</strong></div>
        <div class="detail"><small>National ID</small><strong>{{ $display($member->national_id) }}</strong></div>
        <div class="detail"><small>Voter ID</small><strong>{{ $display($member->voter_id) }}</strong></div>
        <div class="detail"><small>Marital status</small><strong>{{ $display($member->marital_status) }}</strong></div>
        <div class="detail"><small>House number</small><strong>{{ $display($kyc?->house_number) }}</strong></div>
        <div class="detail"><small>Permanent / current address</small><strong>{{ $display($member->physical_address) }}</strong></div>
        <div class="detail"><small>Street / ward</small><strong>{{ $display(collect([$member->street, $member->ward])->filter()->implode(', ')) }}</strong></div>
        <div class="detail"><small>District / region</small><strong>{{ $display(collect([$member->district, $member->region])->filter()->implode(', ')) }}</strong></div>
        <div class="detail"><small>Nearest police station</small><strong>{{ $display($kyc?->police_station) }}</strong></div>
        <div class="detail"><small>Business / work address</small><strong>{{ $display($kyc?->business_address) }}</strong></div>
        <div class="detail"><small>M-Pesa / loan receiving number</small><strong>{{ $display($kyc?->mpesa_phone) }}</strong></div>
        <div class="detail"><small>Bank account number</small><strong>{{ $display($kyc?->bank_account_number) }}</strong></div>
        <div class="detail"><small>Bank account name</small><strong>{{ $display($kyc?->bank_account_name) }}</strong></div>
        <div class="detail"><small>Bank name</small><strong>{{ $display($kyc?->bank_name) }}</strong></div>
        <div class="detail"><small>Housing status</small><strong>{{ $display($kyc?->house_ownership_status) }}</strong></div>
        <div class="detail"><small>Head of household</small><strong>{{ $display($kyc?->head_of_household) }}</strong></div>
        <div class="detail"><small>Number of dependants</small><strong>{{ $kyc?->number_of_dependants ?? 0 }}</strong></div>
        <div class="detail"><small>House roof</small><strong>{{ $display($kyc?->house_roof_type) }}</strong></div>
        <div class="detail"><small>House fence</small><strong>{{ $display($kyc?->house_fence_type) }}</strong></div>
        <div class="detail"><small>VATI family / group relationship</small><strong>Not recorded in member profile</strong></div>
    </div>
</div>

<br>
<div class="card">
    <div class="card-head"><h2>Application terms and loan computation</h2><span>{{ $application->duration_months }} months</span></div>
    <div class="card-body detail-grid">
        <div class="detail"><small>Requested principal</small><strong>{{ $money($application->requested_amount) }}</strong></div>
        <div class="detail"><small>Existing loan balance</small><strong>{{ $money($application->existing_loan_balance) }}</strong></div>
        <div class="detail"><small>Refinancing amount</small><strong>{{ $money($application->refinancing_amount) }}</strong></div>
        <div class="detail"><small>Top-up increment</small><strong>{{ $money($application->increment_amount) }}</strong></div>
        <div class="detail"><small>Annual interest rate</small><strong>{{ number_format((float) $application->product->annual_interest_rate, 2) }}%</strong></div>
        <div class="detail"><small>Interest amount</small><strong>{{ $money($figures['interest']) }}</strong></div>
        <div class="detail"><small>Processing fee</small><strong>{{ $money($figures['processing_fee']) }}</strong></div>
        <div class="detail"><small>Processing fee VAT</small><strong>{{ $money($figures['processing_fee_vat']) }}</strong></div>
        <div class="detail"><small>Transaction fee</small><strong>{{ $money($figures['transaction_fee']) }}</strong></div>
        <div class="detail"><small>Transaction fee VAT</small><strong>{{ $money($figures['transaction_fee_vat']) }}</strong></div>
        <div class="detail"><small>Membership fee</small><strong>{{ $money($figures['membership_fee']) }}</strong></div>
        <div class="detail"><small>Total charges and VAT</small><strong>{{ $money($figures['charges']) }}</strong></div>
        <div class="detail"><small>Security amount</small><strong>{{ $money($figures['security_amount']) }} ({{ number_format((float) $application->product->security_percentage, 2) }}%)</strong></div>
        <div class="detail"><small>Amount receivable</small><strong>{{ $money($figures['amount_receivable']) }}</strong></div>
        <div class="detail"><small>Principal plus interest</small><strong>{{ $money($figures['total_repayment']) }}</strong></div>
        <div class="detail"><small>Repayment plan</small><strong>{{ $installmentCount }} {{ $application->product->repayment_frequency }} installments · {{ $money($figures['total_repayment'] / $installmentCount) }}</strong></div>
        <div class="detail"><small>Current loan cycle</small><strong>{{ str($application->application_type)->replace('_', ' ')->title() }}</strong></div>
        <div class="detail"><small>Loan purpose</small><strong>{{ $display($application->loan_purpose) }}</strong></div>
        <div class="detail"><small>Business summary</small><strong>{{ $display($application->business_summary) }}</strong></div>
    </div>
</div>

<br>
<div class="grid-2 grid-even">
    <div class="card">
        <div class="card-head"><h2>Applicant family members</h2><span>{{ $member->familyMembers->count() }}</span></div>
        <div class="table-wrap"><table><thead><tr><th>Name</th><th>Sex</th><th>Age</th><th>Relationship</th><th>Education</th><th>Marital status</th><th>Occupation</th><th>Secondary occupation</th></tr></thead><tbody>
            @forelse($member->familyMembers as $family)
                <tr><td>{{ $family->name }}</td><td>{{ $display($family->gender) }}</td><td>{{ $family->age ?? '—' }}</td><td>{{ $display($family->relationship) }}</td><td>{{ $display($family->education) }}</td><td>{{ $display($family->marital_status) }}</td><td>{{ $display($family->occupation) }}</td><td>{{ $display($family->secondary_occupation) }}</td></tr>
            @empty<tr><td colspan="8" class="empty">No family-member information recorded.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Family assets / Rasimali</h2><span>{{ $member->assets->count() }}</span></div>
        <div class="table-wrap"><table><thead><tr><th>Asset</th><th>Category</th><th>Quantity</th><th>Estimated value</th><th>Description</th></tr></thead><tbody>
            @forelse($member->assets as $asset)
                <tr><td>{{ $display($asset->assetType?->name) }}</td><td>{{ $display($asset->assetType?->category) }}</td><td>{{ $asset->quantity }}</td><td class="money">{{ $money($asset->estimated_value) }}</td><td>{{ $display($asset->description) }}</td></tr>
            @empty<tr><td colspan="5" class="empty">No family assets recorded.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>

<br>
<div class="grid-2 grid-even">
    <div class="card">
        <div class="card-head"><h2>Income and expenditure assessment</h2></div>
        <div class="card-body detail-grid" style="grid-template-columns:1fr 1fr">
            <div class="detail"><small>Core business income</small><strong>{{ $money($application->assessment?->core_business_income) }}</strong></div>
            <div class="detail"><small>Other / family income</small><strong>{{ $money($application->assessment?->other_income) }}</strong></div>
            <div class="detail"><small>Business expenditure</small><strong>{{ $money($application->assessment?->business_expenses) }}</strong></div>
            <div class="detail"><small>Household expenditure</small><strong>{{ $money($application->assessment?->household_expenses) }}</strong></div>
            <div class="detail"><small>Monthly profit</small><strong>{{ $money($application->assessment?->monthly_profit) }}</strong></div>
            <div class="detail"><small>Disposable income</small><strong>{{ $money($application->assessment?->disposable_income) }}</strong></div>
            <div class="detail"><small>Other institution outstanding debt</small><strong>{{ $money($application->assessment?->existing_external_debt) }}</strong></div>
            <div class="detail"><small>External institution / original amount</small><strong>Not recorded</strong></div>
            <div class="detail"><small>Debt-service ratio</small><strong>{{ $application->assessment?->debt_service_ratio !== null ? number_format((float) $application->assessment->debt_service_ratio, 2).'%' : '—' }}</strong></div>
            <div class="detail"><small>Assessment comment</small><strong>{{ $display($application->assessment?->assessment_comment) }}</strong></div>
            <div class="detail"><small>Household income (member profile)</small><strong>{{ $money($kyc?->household_monthly_income) }}</strong></div>
            <div class="detail"><small>Household expenses (member profile)</small><strong>{{ $money($kyc?->household_monthly_expenses) }}</strong></div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Use of loan amount</h2><span>{{ $money($application->utilizations->sum('allocation_amount')) }}</span></div>
        <div class="table-wrap"><table><thead><tr><th>Use</th><th>Allocation</th><th>Present item value</th></tr></thead><tbody>
            @forelse($application->utilizations as $utilization)
                <tr><td>{{ $utilization->purpose }}</td><td class="money">{{ $money($utilization->allocation_amount) }}</td><td class="money">{{ $money($utilization->current_asset_value) }}</td></tr>
            @empty<tr><td colspan="3" class="empty">No utilization plan recorded.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>

@if($application->term || $application->consent_declaration)
<br>
<div class="card">
    <div class="card-head"><h2>Applicant declaration and accepted terms</h2><span>{{ $application->term?->version ?? 'Captured declaration' }}</span></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail"><small>Consent date</small><strong>{{ $application->consented_at?->format('d M Y H:i') ?? 'Not accepted' }}</strong></div>
            <div class="detail"><small>Cancellation deadline</small><strong>{{ $application->cancellation_deadline?->format('d M Y H:i') ?? 'Not started' }}</strong></div>
            <div class="detail"><small>Applicant signature</small><strong>{{ $application->applicant_signature_path ? 'Captured' : 'Missing' }}</strong></div>
            <div class="detail"><small>Right thumbprint</small><strong>{{ $application->applicant_thumbprint_path ? 'Captured' : 'Missing' }}</strong></div>
        </div>
        <div style="margin-top:16px;padding:16px;background:#f8faf8;border-radius:8px;white-space:pre-wrap;font-size:11px;line-height:1.65">{{ $application->consent_declaration ?: $application->term?->body }}</div>
    </div>
</div>
@endif

<br>
