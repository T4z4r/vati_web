<?php

namespace App\Http\Requests;

use App\Models\LoanProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanApplicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('utilizations')) {
            $this->merge([
                'utilizations' => array_values(array_filter(
                    $this->input('utilizations', []),
                    fn ($row) => filled($row['purpose'] ?? null) || (float) ($row['allocation_amount'] ?? 0) > 0
                )),
            ]);
        }

        if ($this->has('guarantors')) {
            $this->merge([
                'guarantors' => array_values(array_filter(
                    $this->input('guarantors', []),
                    fn ($row) => collect($row)->except('id')->contains(fn ($value) => filled($value))
                )),
            ]);
        }

        if ($this->has('witness_member_ids')) {
            $this->merge([
                'witness_member_ids' => array_values(array_filter(
                    $this->input('witness_member_ids', []),
                    fn ($value) => filled($value)
                )),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = LoanProduct::find($this->input('loan_product_id'));

        return [
            'member_id' => ['sometimes', 'required', 'exists:members,id'],
            'loan_product_id' => ['sometimes', 'required', 'exists:loan_products,id'],
            'application_type' => ['sometimes', 'required', Rule::in(['main', 'refinance', 'top_up'])],
            'requested_amount' => ['sometimes', 'required', 'numeric', 'min:'.($product?->minimum_amount ?? 0), 'max:'.($product?->maximum_amount ?? PHP_INT_MAX)],
            'duration_months' => ['sometimes', 'required', 'integer', 'min:'.($product?->minimum_duration_months ?? 1), 'max:'.($product?->maximum_duration_months ?? 120)],
            'existing_loan_balance' => ['nullable', 'numeric', 'min:0'],
            'refinancing_amount' => ['nullable', 'numeric', 'min:0'],
            'increment_amount' => ['nullable', 'numeric', 'min:0'],
            'loan_purpose' => ['nullable', 'string', 'max:2000'],
            'business_summary' => ['nullable', 'string', 'max:5000'],
            'assessment' => ['nullable', 'array'],
            'assessment.core_business_income' => ['nullable', 'numeric', 'min:0'],
            'assessment.other_income' => ['nullable', 'numeric', 'min:0'],
            'assessment.business_expenses' => ['nullable', 'numeric', 'min:0'],
            'assessment.household_expenses' => ['nullable', 'numeric', 'min:0'],
            'assessment.existing_external_debt' => ['nullable', 'numeric', 'min:0'],
            'assessment.assessment_comment' => ['nullable', 'string', 'max:5000'],
            'utilizations' => ['nullable', 'array'],
            'utilizations.*.purpose' => ['required_with:utilizations', 'string', 'max:255'],
            'utilizations.*.allocation_amount' => ['required_with:utilizations', 'numeric', 'gt:0'],
            'utilizations.*.current_asset_value' => ['nullable', 'numeric', 'min:0'],
            'guarantors' => ['nullable', 'array', 'max:2'],
            'guarantors.*.id' => ['nullable', 'integer'],
            'guarantors.*.guarantor_type' => ['required_with:guarantors', Rule::in(['family', 'non_family'])],
            'guarantors.*.name' => ['required_with:guarantors', 'string', 'max:150'],
            'guarantors.*.relationship' => ['required_with:guarantors', 'string', 'max:100'],
            'guarantors.*.phone' => ['required_with:guarantors', 'string', 'max:20'],
            'guarantors.*.national_id' => ['nullable', 'string', 'max:50'],
            'guarantors.*.voter_id' => ['nullable', 'string', 'max:50'],
            'guarantors.*.house_number' => ['nullable', 'string', 'max:100'],
            'guarantors.*.street' => ['nullable', 'string', 'max:100'],
            'guarantors.*.ward' => ['nullable', 'string', 'max:100'],
            'guarantors.*.district' => ['nullable', 'string', 'max:100'],
            'guarantors.*.region' => ['nullable', 'string', 'max:100'],
            'guarantors.*.business_address' => ['nullable', 'string', 'max:1000'],
            'witness_member_ids' => ['nullable', 'array', 'max:10'],
            'witness_member_ids.*' => ['integer', 'distinct', 'exists:members,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = LoanProduct::find($this->input('loan_product_id'));
            if ($product && ! $product->status) {
                $validator->errors()->add('loan_product_id', 'The selected loan product is inactive.');
            }

            if ($this->filled('utilizations') && $this->filled('requested_amount')) {
                $utilizations = collect($this->input('utilizations', []));
                $allocated = (float) $utilizations->sum('allocation_amount');
                if ($utilizations->isNotEmpty() && abs($allocated - (float) $this->input('requested_amount')) > 0.009) {
                    $validator->errors()->add('utilizations', 'Use-of-funds allocations must total the requested amount.');
                }
            }

            $applicationType = $this->input('application_type');
            if ($applicationType === 'refinance' && $this->filled('refinancing_amount') && (float) $this->input('refinancing_amount') <= 0) {
                $validator->errors()->add('refinancing_amount', 'A refinancing amount is required for refinance applications.');
            }
            if ($applicationType === 'top_up' && $this->filled('increment_amount') && (float) $this->input('increment_amount') <= 0) {
                $validator->errors()->add('increment_amount', 'An increment amount is required for top-up applications.');
            }
            if (in_array($applicationType, ['refinance', 'top_up'], true) && $this->filled('existing_loan_balance') && (float) $this->input('existing_loan_balance') <= 0) {
                $validator->errors()->add('existing_loan_balance', 'The existing outstanding loan balance is required for refinance and top-up applications.');
            }
        });
    }
}
