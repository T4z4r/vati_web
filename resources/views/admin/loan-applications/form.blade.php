@extends('layouts.admin')
@section('title', $application->exists ? 'Edit loan application' : 'New loan application')
@section('content')
    @php
        $editing = $application->exists;
        $assessment = $application->assessment;
        $utilizations = old(
            'utilizations',
            $application->utilizations?->toArray() ?: [
                ['purpose' => 'Working capital', 'allocation_amount' => '', 'current_asset_value' => 0],
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
                    <select name="{{ $editing ? 'member_display' : 'member_id' }}" required @disabled($editing)>
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
                                data-vat="{{ $product->vat_percentage }}" @selected((int) old('loan_product_id', $application->loan_product_id) === $product->id)>
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
                <label>Existing loan balance<input type="number" min="0" name="existing_loan_balance"
                        value="{{ old('existing_loan_balance', $application->existing_loan_balance ?? 0) }}"></label>
                <label>Refinancing amount<input type="number" min="0" name="refinancing_amount"
                        value="{{ old('refinancing_amount', $application->refinancing_amount ?? 0) }}"></label>
                <label>Top-up increment<input type="number" min="0" name="increment_amount"
                        value="{{ old('increment_amount', $application->increment_amount ?? 0) }}"></label>
                <label class="full">Loan purpose
                    <textarea name="loan_purpose" required>{{ old('loan_purpose', $application->loan_purpose) }}</textarea>
                </label>
                <label class="full">Business summary
                    <textarea name="business_summary" required>{{ old('business_summary', $application->business_summary) }}</textarea>
                </label>
            </div>

            <div class="card" style="margin-top:24px">
                <div class="card-head"><h2>Loan computation summary</h2></div>
                <div class="card-body detail-grid">
                    <div class="detail"><small>Estimated total repayment</small><strong id="summary-estimate">TZS 0.00</strong></div>
                    <div class="detail"><small>Estimated charges</small><strong id="summary-charges">TZS 0.00</strong></div>
                    <div class="detail"><small>Estimated amount receivable</small><strong id="summary-receivable">TZS 0.00</strong></div>
                    <div class="detail"><small>Processing fee</small><strong id="summary-processing-fee">TZS 0.00</strong></div>
                    <div class="detail"><small>Transaction fee</small><strong id="summary-transaction-fee">TZS 0.00</strong></div>
                    <div class="detail"><small>Security held</small><strong id="summary-security">TZS 0.00</strong></div>
                </div>
            </div>

            <h3 class="section-title" style="margin-top:25px">Income & expenditure assessment</h3>
            <div class="form-grid">
                <label>Core business income<input type="number" min="0" name="assessment[core_business_income]"
                        value="{{ old('assessment.core_business_income', $assessment?->core_business_income ?? 0) }}"
                        required></label>
                <label>Other income<input type="number" min="0" name="assessment[other_income]"
                        value="{{ old('assessment.other_income', $assessment?->other_income ?? 0) }}"></label>
                <label>Business expenses<input type="number" min="0" name="assessment[business_expenses]"
                        value="{{ old('assessment.business_expenses', $assessment?->business_expenses ?? 0) }}"
                        required></label>
                <label>Household expenses<input type="number" min="0" name="assessment[household_expenses]"
                        value="{{ old('assessment.household_expenses', $assessment?->household_expenses ?? 0) }}"
                        required></label>
                <label>Existing external debt<input type="number" min="0" name="assessment[existing_external_debt]"
                        value="{{ old('assessment.existing_external_debt', $assessment?->existing_external_debt ?? 0) }}"></label>
                <label class="full">Assessment comment
                    <textarea name="assessment[assessment_comment]">{{ old('assessment.assessment_comment', $assessment?->assessment_comment) }}</textarea>
                </label>
            </div>

            <h3 class="section-title" style="margin-top:25px">Use of loan amount</h3>
            <p class="muted">Allocation amounts must total the requested loan amount exactly. Leave unused rows blank.</p>
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
        const product = document.getElementById('product');
        const amount = document.getElementById('amount');
        const months = document.getElementById('months');
        const estimate = document.getElementById('estimate');
        const charges = document.getElementById('charges');
        const receivable = document.getElementById('receivable');
        const allocations = [...document.querySelectorAll('.allocation')];

        function formatMoney(value) {
            return 'TZS ' + Number(value).toLocaleString(undefined, {
                maximumFractionDigits: 2,
                minimumFractionDigits: 2
            });
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
                document.getElementById('summary-processing-fee').textContent = formatMoney(processingFee + processingFeeVat);
                document.getElementById('summary-transaction-fee').textContent = formatMoney(transactionFee + transactionFeeVat);
                document.getElementById('summary-security').textContent = formatMoney(securityAmount);
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
            }
            const allocated = allocations.reduce((sum, input) => sum + Number(input.value || 0), 0);
            document.getElementById('allocated-total').textContent = 'TZS ' + allocated.toLocaleString();
        }
        [product, amount, months, ...allocations].forEach(input => input.addEventListener('input', calculate));
        calculate();
    </script>
@endpush
