<h2 class="section-title">Loan application identification</h2>
    <table class="detail-table">
        <tbody>
            <tr><th>Master roll / membership number</th><td>{{ $member->membership_number }}</td></tr>
            <tr><th>Branch / Tawi</th><td>{{ $display($application->branch?->branch_name) }}</td></tr>
            <tr><th>Area / Eneo</th><td>{{ $display($application->branch?->area?->name) }}</td></tr>
            <tr><th>Region / Mkoa</th><td>{{ $display($application->branch?->area?->region?->name) }}</td></tr>
            <tr><th>Group / Kikundi</th><td>{{ $display($application->group?->group_name) }}</td></tr>
            <tr><th>Application type</th><td>{{ str($application->application_type)->replace('_', ' ')->title() }}</td></tr>
            <tr><th>Application date</th><td>{{ $application->created_at?->format('d M Y') }}</td></tr>
            <tr><th>Submitted date</th><td>{{ $application->submitted_at?->format('d M Y H:i') ?? 'Not submitted' }}</td></tr>
            <tr><th>Loan disbursement date</th><td>{{ $application->loan?->disbursement_date?->format('d M Y') ?? 'Not disbursed' }}</td></tr>
        </tbody>
    </table>

<br>
<h2 class="section-title">Applicant personal profile / Taarifa binafsi</h2>
    <table class="detail-table">
        <tbody>
            <tr><th>Applicant name</th><td>{{ $fullName }}</td></tr>
            <tr><th>Father / husband / legal guardian</th><td>{{ $display($member->guardian_name) }}</td></tr>
            <tr><th>Occupation</th><td>{{ $display($member->occupation) }}</td></tr>
            <tr><th>Age</th><td>{{ $member->date_of_birth?->age ?? 'Not recorded' }}</td></tr>
            <tr><th>Date of birth</th><td>{{ $member->date_of_birth?->format('d M Y') ?? 'Not recorded' }}</td></tr>
            <tr><th>Gender</th><td>{{ $display($member->gender) }}</td></tr>
            <tr><th>Religion</th><td>Not recorded in member profile</td></tr>
            <tr><th>Phone</th><td>{{ $display($member->phone) }}</td></tr>
            <tr><th>Alternate phone</th><td>{{ $display($member->alternate_phone) }}</td></tr>
            <tr><th>Nationality</th><td>{{ $display($member->nationality) }}</td></tr>
            <tr><th>National ID</th><td>{{ $display($member->national_id) }}</td></tr>
            <tr><th>Voter ID</th><td>{{ $display($member->voter_id) }}</td></tr>
            <tr><th>Marital status</th><td>{{ $display($member->marital_status) }}</td></tr>
            <tr><th>House number</th><td>{{ $display($kyc?->house_number) }}</td></tr>
            <tr><th>Permanent / current address</th><td>{{ $display($member->physical_address) }}</td></tr>
            <tr><th>Street / ward</th><td>{{ $display(collect([$member->street, $member->ward])->filter()->implode(', ')) }}</td></tr>
            <tr><th>District / region</th><td>{{ $display(collect([$member->district, $member->region])->filter()->implode(', ')) }}</td></tr>
            <tr><th>Nearest police station</th><td>{{ $display($kyc?->police_station) }}</td></tr>
            <tr><th>Business / work address</th><td>{{ $display($kyc?->business_address) }}</td></tr>
            <tr><th>M-Pesa / loan receiving number</th><td>{{ $display($kyc?->mpesa_phone) }}</td></tr>
            <tr><th>Bank account number</th><td>{{ $display($kyc?->bank_account_number) }}</td></tr>
            <tr><th>Bank account name</th><td>{{ $display($kyc?->bank_account_name) }}</td></tr>
            <tr><th>Bank name</th><td>{{ $display($kyc?->bank_name) }}</td></tr>
            <tr><th>Housing status</th><td>{{ $display($kyc?->house_ownership_status) }}</td></tr>
            <tr><th>Head of household</th><td>{{ $display($kyc?->head_of_household) }}</td></tr>
            <tr><th>Number of dependants</th><td>{{ $kyc?->number_of_dependants ?? 0 }}</td></tr>
            <tr><th>House roof</th><td>{{ $display($kyc?->house_roof_type) }}</td></tr>
            <tr><th>House fence</th><td>{{ $display($kyc?->house_fence_type) }}</td></tr>
            <tr><th>VATI family / group relationship</th><td>Not recorded in member profile</td></tr>
        </tbody>
    </table>

<br>
<h2 class="section-title">Application terms and loan computation</h2>
    <table class="detail-table">
        <tbody>
            <tr><th>Requested principal</th><td class="money">{{ $money($application->requested_amount) }}</td></tr>
            <tr><th>Existing loan balance</th><td class="money">{{ $money($application->existing_loan_balance) }}</td></tr>
            <tr><th>Refinancing amount</th><td class="money">{{ $money($application->refinancing_amount) }}</td></tr>
            <tr><th>Top-up increment</th><td class="money">{{ $money($application->increment_amount) }}</td></tr>
            <tr><th>Annual interest rate</th><td>{{ number_format((float) $application->product->annual_interest_rate, 2) }}%</td></tr>
            <tr><th>Interest amount</th><td class="money">{{ $money($figures['interest']) }}</td></tr>
            <tr><th>Processing fee</th><td class="money">{{ $money($figures['processing_fee']) }}</td></tr>
            <tr><th>Insurance</th><td class="money">{{ $money($figures['insurance_fee']) }}</td></tr>
            <tr><th>VAT</th><td class="money">{{ $money($figures['vat']) }}</td></tr>
            <tr><th>Total charges and VAT</th><td class="money">{{ $money($figures['charges']) }}</td></tr>
            <tr><th>Security amount</th><td class="money">{{ $money($figures['security_amount']) }} ({{ number_format((float) $application->product->security_percentage, 2) }}%)</td></tr>
            <tr><th>Amount receivable</th><td class="money">{{ $money($figures['amount_receivable']) }}</td></tr>
            <tr><th>Principal plus interest</th><td class="money">{{ $money($figures['total_repayment']) }}</td></tr>
            <tr><th>Repayment plan</th><td>{{ $installmentCount }} {{ $application->product->repayment_frequency }} installments · {{ $money($figures['installment_amount']) }}</td></tr>
            <tr><th>Current loan cycle</th><td>{{ str($application->application_type)->replace('_', ' ')->title() }}</td></tr>
            <tr><th>Loan purpose</th><td>{{ $display($application->loan_purpose) }}</td></tr>
            <tr><th>Business summary</th><td>{{ $display($application->business_summary) }}</td></tr>
        </tbody>
    </table>

<br>
<div class="grid-2 grid-even">
    <div class="card">
        <div class="card-head"><h2>Applicant family members</h2><span>{{ $member->familyMembers->count() }}</span></div>
        <div class="table-wrap"><table><thead><tr><th>Name</th><th>Sex</th><th>Age</th><th>Relationship</th><th>Education</th><th>Marital status</th><th>Occupation</th><th>Secondary occupation</th></tr></thead><tbody>
            @forelse($member->familyMembers as $family)
                <tr><td>{{ $family->name }}</td><td>{{ $display($family->gender) }}</td><td>{{ $family->age ?? '—' }}</td><td>{{ $display($family->relationship) }}</td><td>{{ $display($family->education) }}</td><td>{{ $display($family->marital_status) }}</td><td>{{ $display($family->occupation) }}</td><td>{{ $display($family->secondary_occupation) }}</td></tr>
            @empty<tr><td colspan="8" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No family-member information recorded.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Family assets / Rasimali</h2><span>{{ $member->assets->count() }}</span></div>
        <div class="table-wrap"><table><thead><tr><th>Asset</th><th>Category</th><th>Quantity</th><th>Estimated value</th><th>Description</th></tr></thead><tbody>
            @forelse($member->assets as $asset)
                <tr><td>{{ $display($asset->assetType?->name) }}</td><td>{{ $display($asset->assetType?->category) }}</td><td>{{ $asset->quantity }}</td><td class="money">{{ $money($asset->estimated_value) }}</td><td>{{ $display($asset->description) }}</td></tr>
            @empty<tr><td colspan="5" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No family assets recorded.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>

<br>
<div class="grid-2 grid-even">
    <div>
        <h2 class="section-title">Income and expenditure assessment</h2>
        <table class="detail-table">
            <tbody>
                <tr><th>Core business income</th><td class="money">{{ $money($application->assessment?->core_business_income) }}</td></tr>
                <tr><th>Other / family income</th><td class="money">{{ $money($application->assessment?->other_income) }}</td></tr>
                <tr><th>Business expenditure</th><td class="money">{{ $money($application->assessment?->business_expenses) }}</td></tr>
                <tr><th>Household expenditure</th><td class="money">{{ $money($application->assessment?->household_expenses) }}</td></tr>
                <tr><th>Monthly profit</th><td class="money">{{ $money($application->assessment?->monthly_profit) }}</td></tr>
                <tr><th>Disposable income</th><td class="money">{{ $money($application->assessment?->disposable_income) }}</td></tr>
                <tr><th>Other institution outstanding debt</th><td class="money">{{ $money($application->assessment?->existing_external_debt) }}</td></tr>
                <tr><th>External institution / original amount</th><td>Not recorded</td></tr>
                <tr><th>Debt-service ratio</th><td>{{ $application->assessment?->debt_service_ratio !== null ? number_format((float) $application->assessment->debt_service_ratio, 2).'%' : '—' }}</td></tr>
                <tr><th>Assessment comment</th><td>{{ $display($application->assessment?->assessment_comment) }}</td></tr>
                <tr><th>Household income (member profile)</th><td class="money">{{ $money($kyc?->household_monthly_income) }}</td></tr>
                <tr><th>Household expenses (member profile)</th><td class="money">{{ $money($kyc?->household_monthly_expenses) }}</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card">
        <div class="card-head"><h2>Use of loan amount</h2><span>{{ $money($application->utilizations->sum('allocation_amount')) }}</span></div>
        <div class="table-wrap"><table><thead><tr><th>Use</th><th>Allocation</th><th>Present item value</th></tr></thead><tbody>
            @forelse($application->utilizations as $utilization)
                <tr><td>{{ $utilization->purpose }}</td><td class="money">{{ $money($utilization->allocation_amount) }}</td><td class="money">{{ $money($utilization->current_asset_value) }}</td></tr>
            @empty<tr><td colspan="3" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No utilization plan recorded.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>

@if($application->term || $application->consent_declaration)
<br>
<h2 class="section-title">Applicant declaration and accepted terms</h2>
    <table class="detail-table">
        <tbody>
            <tr><th>Terms version</th><td>{{ $application->term?->version ?? 'Captured declaration' }}</td></tr>
            <tr><th>Consent date</th><td>{{ $application->consented_at?->format('d M Y H:i') ?? 'Not accepted' }}</td></tr>
            <tr><th>Cancellation deadline</th><td>{{ $application->cancellation_deadline?->format('d M Y H:i') ?? 'Not started' }}</td></tr>
            <tr><th>Applicant signature</th><td>{{ $application->applicant_signature_path ? 'Captured' : 'Missing' }}</td></tr>
            <tr><th>Right thumbprint</th><td>{{ $application->applicant_thumbprint_path ? 'Captured' : 'Missing' }}</td></tr>
        </tbody>
    </table>
    <div style="padding:16px;background:#f8faf8;border-radius:8px;white-space:pre-wrap;font-size:11px;line-height:1.65">{{ $application->consent_declaration ?: $application->term?->body }}</div>
@endif

<br>
