@extends('layouts.admin')
@section('title', $application->exists ? 'Edit loan application' : 'New loan application')
@section('content')
    @php
        $editing = $application->exists;
        $assessment = $application->assessment;
        $utilizations = old(
            'utilizations',
            $application->utilizations?->toArray() ?: [
                ['purpose' => '', 'allocation_amount' => '', 'current_asset_value' => 0],
                ['purpose' => '', 'allocation_amount' => '', 'current_asset_value' => 0],
                ['purpose' => '', 'allocation_amount' => '', 'current_asset_value' => 0],
            ],
        );
    @endphp

    <div class="page-head">
        <div>
            <p class="eyebrow">CREDIT ONBOARDING</p>
            <h1>{{ $editing ? 'Edit draft application' : 'Create loan application' }}</h1>
            <p>Capture terms, affordability, and the complete use-of-funds plan.</p>
        </div>
        <a class="btn btn-secondary"
            href="{{ $editing ? route('admin.loan-applications.show', $application) : route('admin.loan-applications.index') }}">←
            Back</a>
    </div>

    <form class="card" method="POST"
        action="{{ $editing ? route('admin.loan-applications.update', $application) : route('admin.loan-applications.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <div class="card-body">
            @if ($editing)
                <div class="alert alert-danger">Saving financial changes invalidates the previous applicant consent and
                    biometric confirmation. Capture them again before submission.</div>
            @endif
            <h3 class="section-title">Application terms</h3>
            <div class="form-grid">
                <label>Member
                    @if ($editing)
                        <input type="hidden" name="member_id" value="{{ $application->member_id }}">
                    @endif
                    <select id="member-select" name="{{ $editing ? 'member_display' : 'member_id' }}" required @disabled($editing)>
                        <option value="">Select active member</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected((int) old('member_id', $selectedMember) === $member->id)>
                                {{ $member->membership_number }} · {{ $member->first_name }} {{ $member->last_name }} ·
                                {{ $member->group->group_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Loan product
                    <select id="product" name="loan_product_id" required>
                        <option value="">Select product</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" data-min="{{ $product->minimum_amount }}"
                                data-max="{{ $product->maximum_amount }}" data-rate="{{ $product->annual_interest_rate }}"
                                data-minmonths="{{ $product->minimum_duration_months }}"
                                data-maxmonths="{{ $product->maximum_duration_months }}"
                                data-processing-fee="{{ $product->processing_fee_percentage }}"
                                data-transaction-fee="{{ $product->transaction_fee_percentage }}"
                                data-security-percentage="{{ $product->security_percentage }}"
                                data-membership-fee="{{ $product->membership_fee }}"
                                data-vat="{{ $product->vat_percentage }}"
                                data-frequency="{{ $product->repayment_frequency }}"
                                data-interest-method="{{ $product->interest_method }}" @selected((int) old('loan_product_id', $application->loan_product_id) === $product->id)>
                                {{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Application type
                    <select id="application-type" name="application_type" required>
                        @foreach (['main' => 'Main loan', 'refinance' => 'Refinancing', 'top_up' => 'Top up'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('application_type', $application->application_type ?: 'main') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Requested amount (TZS)<input id="amount" type="number" min="1" name="requested_amount"
                        value="{{ old('requested_amount', $application->requested_amount) }}" required></label>
                <label>Duration (months)<input id="months" type="number" min="1" name="duration_months"
                        value="{{ old('duration_months', $application->duration_months) }}" required></label>
                <label>Estimated total repayment<input id="estimate" readonly
                        placeholder="Choose product and terms"></label>
                <label>Estimated charges<input id="charges" readonly placeholder="Choose product and terms"></label>
                <label>Estimated amount receivable<input id="receivable" readonly
                        placeholder="Choose product and terms"></label>
                <label>Existing loan balance<input id="existing-loan-balance" type="number" min="0" name="existing_loan_balance"
                        value="{{ old('existing_loan_balance', $application->existing_loan_balance ?? 0) }}"></label>
                <label>Refinancing amount<input type="number" min="0" name="refinancing_amount"
                        value="{{ old('refinancing_amount', $application->refinancing_amount ?? 0) }}"></label>
                <label>Top-up increment<input type="number" min="0" name="increment_amount"
                        value="{{ old('increment_amount', $application->increment_amount ?? 0) }}"></label>
                <label class="full">Loan purpose
                    <textarea name="loan_purpose" required>{{ old('loan_purpose', $application->loan_purpose) }}</textarea>
                </label>
                <label class="full">Business summary <span class="muted">(optional)</span>
                    <textarea id="business-summary" name="business_summary">{{ old('business_summary', $application->business_summary) }}</textarea>
                </label>
            </div>

            <div id="member-profile" class="card" style="margin-top:24px;display:none">
                <div class="card-head">
                    <div><h2>Applicant profile (auto-populated)</h2><small>Read-only information from the selected member record</small></div>
                    <span id="profile-member-number" class="badge active"></span>
                </div>
                <div class="card-body">
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
                        <img id="profile-member-photo" src="" alt="Selected member photograph"
                            style="display:none;width:112px;height:112px;border-radius:14px;object-fit:cover;border:1px solid var(--border)">
                        <div id="profile-member-photo-fallback" class="avatar"
                            style="width:112px;height:112px;border-radius:14px;font-size:28px">—</div>
                        <div>
                            <small class="muted">Applicant photograph</small>
                            <h3 id="profile-member-photo-name" style="margin:4px 0 0">Selected member</h3>
                            <p id="profile-member-photo-status" class="muted" style="margin:4px 0 0">No photograph available</p>
                        </div>
                    </div>
                    <h3 class="section-title">Personal and organization information</h3>
                    <div class="detail-grid">
                        <div class="detail"><small>Applicant name</small><strong data-member-field="full_name">—</strong></div>
                        <div class="detail"><small>Father / husband / guardian</small><strong data-member-field="guardian_name">—</strong></div>
                        <div class="detail"><small>Occupation</small><strong data-member-field="occupation">—</strong></div>
                        <div class="detail"><small>Age</small><strong data-member-field="age">—</strong></div>
                        <div class="detail"><small>Date of birth</small><strong data-member-field="date_of_birth">—</strong></div>
                        <div class="detail"><small>Gender</small><strong data-member-field="gender">—</strong></div>
                        <div class="detail"><small>Marital status</small><strong data-member-field="marital_status">—</strong></div>
                        <div class="detail"><small>Nationality</small><strong data-member-field="nationality">—</strong></div>
                        <div class="detail"><small>Phone</small><strong data-member-field="phone">—</strong></div>
                        <div class="detail"><small>National / voter ID</small><strong data-member-field="identification">—</strong></div>
                        <div class="detail"><small>Branch</small><strong data-member-field="branch">—</strong></div>
                        <div class="detail"><small>Area / region</small><strong data-member-field="organization_location">—</strong></div>
                        <div class="detail"><small>Group</small><strong data-member-field="group">—</strong></div>
                        <div class="detail"><small>Meeting day / location</small><strong data-member-field="group_meeting">—</strong></div>
                        <div class="detail"><small>Loan officer</small><strong data-member-field="loan_officer">—</strong></div>
                    </div>

                    <h3 class="section-title" style="margin-top:22px">Residence, business and banking</h3>
                    <div class="detail-grid">
                        <div class="detail"><small>House number</small><strong data-member-field="house_number">—</strong></div>
                        <div class="detail"><small>Physical address</small><strong data-member-field="physical_address">—</strong></div>
                        <div class="detail"><small>Street / ward</small><strong data-member-field="member_location">—</strong></div>
                        <div class="detail"><small>District / region</small><strong data-member-field="member_region">—</strong></div>
                        <div class="detail"><small>Nearest police station</small><strong data-member-field="police_station">—</strong></div>
                        <div class="detail"><small>Business / work location</small><strong data-member-field="business_profile">—</strong></div>
                        <div class="detail"><small>M-Pesa / receiving number</small><strong data-member-field="mpesa_phone">—</strong></div>
                        <div class="detail"><small>Bank account</small><strong data-member-field="bank_profile">—</strong></div>
                        <div class="detail"><small>Housing status</small><strong data-member-field="house_ownership_status">—</strong></div>
                        <div class="detail"><small>Head of household</small><strong data-member-field="head_of_household">—</strong></div>
                        <div class="detail"><small>Dependants</small><strong data-member-field="number_of_dependants">—</strong></div>
                        <div class="detail"><small>House roof / fence</small><strong data-member-field="house_structure">—</strong></div>
                        <div class="detail"><small>Monthly household income</small><strong data-member-field="household_monthly_income">—</strong></div>
                        <div class="detail"><small>Monthly household expenses</small><strong data-member-field="household_monthly_expenses">—</strong></div>
                        <div class="detail"><small>Existing VATI loan balance</small><strong data-member-field="current_loan_balance">—</strong></div>
                        <div class="detail"><small>Family / assets / nominees</small><strong data-member-field="record_counts">—</strong></div>
                    </div>
                    <p class="muted" style="margin:16px 0 0">Update incorrect information from the member profile before creating this application.</p>
                </div>
            </div>

            <div class="card" style="margin-top:24px">
                <div class="card-head">
                    <h2>Loan computation summary</h2>
                </div>
                <div class="card-body detail-grid">
                    <div class="detail"><small>Estimated total repayment</small><strong id="summary-estimate">TZS
                            0.00</strong></div>
                    <div class="detail"><small>Estimated charges</small><strong id="summary-charges">TZS 0.00</strong></div>
                    <div class="detail"><small>Estimated amount receivable</small><strong id="summary-receivable">TZS
                            0.00</strong></div>
                    <div class="detail"><small>Processing fee</small><strong id="summary-processing-fee">TZS 0.00</strong>
                    </div>
                    <div class="detail"><small>Transaction fee</small><strong id="summary-transaction-fee">TZS 0.00</strong>
                    </div>
                    <div class="detail"><small>Security held</small><strong id="summary-security">TZS 0.00</strong></div>
                </div>
            </div>

            <div id="repayment-schedule" class="card" style="margin-top:24px;display:none">
                <div class="card-head">
                    <div>
                        <h2>Projected repayment schedule</h2>
                        <small id="schedule-description">Calculated from the current loan application terms</small>
                    </div>
                    <span id="schedule-frequency" class="badge active"></span>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Installment</th>
                                    <th>Repayment period</th>
                                    <th>Principal (TZS)</th>
                                    <th>Interest (TZS)</th>
                                    <th>Total installment (TZS)</th>
                                    <th>Balance after payment (TZS)</th>
                                </tr>
                            </thead>
                            <tbody id="schedule-body"></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">Totals</th>
                                    <th id="schedule-principal-total">0.00</th>
                                    <th id="schedule-interest-total">0.00</th>
                                    <th id="schedule-repayment-total">0.00</th>
                                    <th>0.00</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="muted" style="margin:12px 0 0">This is an application-stage projection. Actual due dates are assigned when the approved loan is disbursed.</p>
                </div>
            </div>

            <h3 class="section-title" style="margin-top:25px">Income & expenditure assessment</h3>
            <div class="form-grid">
                <label>Core business income<input id="core-business-income" type="number" min="0" name="assessment[core_business_income]"
                        value="{{ old('assessment.core_business_income', $assessment?->core_business_income ?? 0) }}"
                        required></label>
                <label>Other income<input type="number" min="0" name="assessment[other_income]"
                        value="{{ old('assessment.other_income', $assessment?->other_income ?? 0) }}"></label>
                <label>Business expenses<input type="number" min="0" name="assessment[business_expenses]"
                        value="{{ old('assessment.business_expenses', $assessment?->business_expenses ?? 0) }}"
                        required></label>
                <label>Household expenses<input id="household-expenses" type="number" min="0" name="assessment[household_expenses]"
                        value="{{ old('assessment.household_expenses', $assessment?->household_expenses ?? 0) }}"
                        required></label>
                <label>Existing external debt<input type="number" min="0"
                        name="assessment[existing_external_debt]"
                        value="{{ old('assessment.existing_external_debt', $assessment?->existing_external_debt ?? 0) }}"></label>
                <label class="full">Assessment comment
                    <textarea name="assessment[assessment_comment]">{{ old('assessment.assessment_comment', $assessment?->assessment_comment) }}</textarea>
                </label>
            </div>

            <h3 class="section-title" style="margin-top:25px">Use of loan amount <small class="muted">(Optional)</small></h3>
            <p class="muted">You may leave this section blank. If provided, allocation amounts must total the requested loan amount exactly.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Purpose</th>
                            <th>Allocation (TZS)</th>
                            <th>Present asset value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($utilizations as $index => $utilization)
                            <tr>
                                <td><input name="utilizations[{{ $index }}][purpose]"
                                        value="{{ $utilization['purpose'] ?? '' }}" placeholder="e.g. Working capital">
                                </td>
                                <td><input class="allocation" type="number" min="0"
                                        name="utilizations[{{ $index }}][allocation_amount]"
                                        value="{{ $utilization['allocation_amount'] ?? '' }}"></td>
                                <td><input type="number" min="0"
                                        name="utilizations[{{ $index }}][current_asset_value]"
                                        value="{{ $utilization['current_asset_value'] ?? 0 }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted" style="margin-top:10px">Allocated: <strong id="allocated-total">TZS 0</strong></p>

            <div class="form-actions">
                <a class="btn btn-secondary"
                    href="{{ $editing ? route('admin.loan-applications.show', $application) : route('admin.loan-applications.index') }}">Cancel</a>
                <button
                    class="btn btn-primary">{{ $editing ? 'Save draft changes' : 'Create draft application' }}</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        const memberProfiles = @json($memberProfiles);
        const memberSelect = document.getElementById('member-select');
        const memberProfile = document.getElementById('member-profile');
        const memberPhoto = document.getElementById('profile-member-photo');
        const memberPhotoFallback = document.getElementById('profile-member-photo-fallback');
        const canAutofillApplication = @json(!$editing && !session()->hasOldInput());
        const product = document.getElementById('product');
        const amount = document.getElementById('amount');
        const months = document.getElementById('months');
        const estimate = document.getElementById('estimate');
        const charges = document.getElementById('charges');
        const receivable = document.getElementById('receivable');
        const allocations = [...document.querySelectorAll('.allocation')];
        const repaymentSchedule = document.getElementById('repayment-schedule');
        const scheduleBody = document.getElementById('schedule-body');

        function formatMoney(value) {
            return 'TZS ' + Number(value).toLocaleString(undefined, {
                maximumFractionDigits: 2,
                minimumFractionDigits: 2
            });
        }

        function text(...values) {
            return values.filter(value => value !== null && value !== undefined && String(value).trim() !== '').join(' · ') || '—';
        }

        function showMemberProfile() {
            const profile = memberProfiles[memberSelect.value];
            memberProfile.style.display = profile ? '' : 'none';
            if (!profile) return;

            const values = {
                ...profile,
                identification: text(profile.national_id, profile.voter_id),
                organization_location: text(profile.area, profile.organization_region),
                group_meeting: text(profile.meeting_day, profile.group_location),
                member_location: text(profile.street, profile.ward),
                member_region: text(profile.district, profile.region),
                business_profile: text(profile.business_name, profile.business_type, profile.business_address),
                bank_profile: text(profile.bank_account_number, profile.bank_account_name, profile.bank_name),
                house_structure: text(profile.house_roof_type, profile.house_fence_type),
                household_monthly_income: formatMoney(profile.household_monthly_income || 0),
                household_monthly_expenses: formatMoney(profile.household_monthly_expenses || 0),
                current_loan_balance: formatMoney(profile.current_loan_balance || 0),
                record_counts: `${profile.family_members_count} family · ${profile.assets_count} assets · ${profile.nominees_count} nominees`,
            };

            document.querySelectorAll('[data-member-field]').forEach(element => {
                element.textContent = text(values[element.dataset.memberField]);
            });
            document.getElementById('profile-member-number').textContent = profile.membership_number;
            document.getElementById('profile-member-photo-name').textContent = profile.full_name;
            memberPhoto.alt = `${profile.full_name} photograph`;
            memberPhotoFallback.textContent = profile.initials || '—';

            if (profile.photo_url) {
                memberPhoto.src = profile.photo_url;
                memberPhoto.style.display = '';
                memberPhotoFallback.style.display = 'none';
                document.getElementById('profile-member-photo-status').textContent = 'Photograph from member profile';
            } else {
                memberPhoto.removeAttribute('src');
                memberPhoto.style.display = 'none';
                memberPhotoFallback.style.display = 'flex';
                document.getElementById('profile-member-photo-status').textContent = 'No photograph has been uploaded for this member';
            }

            if (canAutofillApplication) {
                document.getElementById('existing-loan-balance').value = Number(profile.current_loan_balance || 0);
                document.getElementById('household-expenses').value = Number(profile.household_monthly_expenses || 0);
                document.getElementById('business-summary').value = text(profile.business_name, profile.business_type, profile.business_address) === '—'
                    ? ''
                    : text(profile.business_name, profile.business_type, profile.business_address);
            }
        }

        function renderRepaymentSchedule(principal, interest, duration, frequency) {
            const memberSelected = Boolean(memberProfiles[memberSelect.value]);
            const installmentCount = frequency === 'weekly'
                ? Math.max(1, Math.round(duration * 52 / 12))
                : Math.max(1, duration);

            repaymentSchedule.style.display = memberSelected ? '' : 'none';
            scheduleBody.innerHTML = '';

            if (!memberSelected || !principal || !duration || !installmentCount) {
                return;
            }

            const principalPart = Math.round((principal / installmentCount) * 100) / 100;
            const interestPart = Math.round((interest / installmentCount) * 100) / 100;
            let allocatedPrincipal = 0;
            let allocatedInterest = 0;

            for (let number = 1; number <= installmentCount; number++) {
                const isLast = number === installmentCount;
                const principalDue = isLast ? principal - allocatedPrincipal : principalPart;
                const interestDue = isLast ? interest - allocatedInterest : interestPart;
                allocatedPrincipal += principalDue;
                allocatedInterest += interestDue;
                const balance = Math.max(0, principal + interest - allocatedPrincipal - allocatedInterest);
                const row = document.createElement('tr');
                const values = [
                    number,
                    `${frequency === 'weekly' ? 'Week' : 'Month'} ${number}`,
                    principalDue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    interestDue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    (principalDue + interestDue).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    balance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                ];
                values.forEach(value => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                scheduleBody.appendChild(row);
            }

            const frequencyLabel = frequency === 'weekly' ? 'Weekly' : 'Monthly';
            document.getElementById('schedule-frequency').textContent = `${installmentCount} ${frequencyLabel.toLowerCase()} installments`;
            document.getElementById('schedule-description').textContent = `${frequencyLabel} projection for the selected applicant and product`;
            document.getElementById('schedule-principal-total').textContent = principal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('schedule-interest-total').textContent = interest.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('schedule-repayment-total').textContent = (principal + interest).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function calculate() {
            const option = product.selectedOptions[0];
            const principal = Number(amount.value);
            const duration = Number(months.value);
            if (option?.dataset.rate && principal && duration) {
                amount.min = option.dataset.min;
                amount.max = option.dataset.max;
                months.min = option.dataset.minmonths;
                months.max = option.dataset.maxmonths;
                const rate = Number(option.dataset.rate) / 100;
                const interest = principal * rate * (duration / 12);
                const processingFee = principal * (Number(option.dataset.processingFee) / 100);
                const transactionFee = principal * (Number(option.dataset.transactionFee) / 100);
                const securityAmount = principal * (Number(option.dataset.securityPercentage) / 100);
                const membershipFee = Number(option.dataset.membershipFee) || 0;
                const vatRate = Number(option.dataset.vat) / 100;
                const processingFeeVat = processingFee * vatRate;
                const transactionFeeVat = transactionFee * vatRate;
                const totalCharges = processingFee + processingFeeVat + transactionFee + transactionFeeVat + membershipFee;
                const receivableAmount = principal - (totalCharges + securityAmount);
                const totalRepayment = principal + interest;

                estimate.value = formatMoney(totalRepayment);
                charges.value = formatMoney(totalCharges);
                receivable.value = formatMoney(receivableAmount);

                document.getElementById('summary-estimate').textContent = formatMoney(totalRepayment);
                document.getElementById('summary-charges').textContent = formatMoney(totalCharges);
                document.getElementById('summary-receivable').textContent = formatMoney(receivableAmount);
                document.getElementById('summary-processing-fee').textContent = formatMoney(processingFee +
                    processingFeeVat);
                document.getElementById('summary-transaction-fee').textContent = formatMoney(transactionFee +
                    transactionFeeVat);
                document.getElementById('summary-security').textContent = formatMoney(securityAmount);
                renderRepaymentSchedule(principal, interest, duration, option.dataset.frequency || 'monthly');
            } else {
                estimate.value = '';
                charges.value = '';
                receivable.value = '';
                document.getElementById('summary-estimate').textContent = 'TZS 0.00';
                document.getElementById('summary-charges').textContent = 'TZS 0.00';
                document.getElementById('summary-receivable').textContent = 'TZS 0.00';
                document.getElementById('summary-processing-fee').textContent = 'TZS 0.00';
                document.getElementById('summary-transaction-fee').textContent = 'TZS 0.00';
                document.getElementById('summary-security').textContent = 'TZS 0.00';
                repaymentSchedule.style.display = memberProfiles[memberSelect.value] ? '' : 'none';
                scheduleBody.innerHTML = '<tr><td colspan="6" class="muted">Select a loan product and enter a valid amount and duration to generate the schedule.</td></tr>';
            }
            const allocated = allocations.reduce((sum, input) => sum + Number(input.value || 0), 0);
            document.getElementById('allocated-total').textContent = 'TZS ' + allocated.toLocaleString();
        }
        [product, amount, months, ...allocations].forEach(input => input.addEventListener('input', calculate));
        memberSelect.addEventListener('change', () => {
            showMemberProfile();
            calculate();
        });
        showMemberProfile();
        calculate();
    </script>
@endpush
