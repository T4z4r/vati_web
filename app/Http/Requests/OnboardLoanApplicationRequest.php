<?php

namespace App\Http\Requests;

use App\Models\LoanProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardLoanApplicationRequest extends FormRequest
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
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = LoanProduct::find($this->input('loan_product_id'));

        return [
            'member_id' => ['required', 'exists:members,id'],
            'loan_product_id' => ['required', 'exists:loan_products,id'],
            'application_type' => ['required', Rule::in(['main', 'refinance', 'top_up'])],
            'requested_amount' => ['required', 'numeric', 'min:'.($product?->minimum_amount ?? 0), 'max:'.($product?->maximum_amount ?? PHP_INT_MAX)],
            'duration_months' => ['required', 'integer', 'min:'.($product?->minimum_duration_months ?? 1), 'max:'.($product?->maximum_duration_months ?? 120)],
            'existing_loan_balance' => ['nullable', 'numeric', 'min:0'],
            'refinancing_amount' => ['nullable', 'numeric', 'min:0'],
            'increment_amount' => ['nullable', 'numeric', 'min:0'],
            'loan_purpose' => ['required', 'string', 'max:2000'],
            'business_summary' => ['nullable', 'string', 'max:5000'],
            'assessment' => ['required', 'array'],
            'assessment.core_business_income' => ['required', 'numeric', 'min:0'],
            'assessment.other_income' => ['nullable', 'numeric', 'min:0'],
            'assessment.business_expenses' => ['required', 'numeric', 'min:0'],
            'assessment.household_expenses' => ['required', 'numeric', 'min:0'],
            'assessment.existing_external_debt' => ['nullable', 'numeric', 'min:0'],
            'assessment.assessment_comment' => ['nullable', 'string', 'max:5000'],
            'utilizations' => ['nullable', 'array'],
            'utilizations.*.purpose' => ['required', 'string', 'max:255'],
            'utilizations.*.allocation_amount' => ['required', 'numeric', 'gt:0'],
            'utilizations.*.current_asset_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = LoanProduct::find($this->input('loan_product_id'));
            if ($product && ! $product->status) {
                $validator->errors()->add('loan_product_id', 'The selected loan product is inactive.');
            }

            $utilizations = collect($this->input('utilizations', []));
            $allocated = (float) $utilizations->sum('allocation_amount');
            if ($utilizations->isNotEmpty() && abs($allocated - (float) $this->input('requested_amount')) > 0.009) {
                $validator->errors()->add('utilizations', 'Use-of-funds allocations must total the requested amount.');
            }

            if ($this->input('application_type') === 'refinance' && (float) $this->input('refinancing_amount') <= 0) {
                $validator->errors()->add('refinancing_amount', 'A refinancing amount is required for refinance applications.');
            }
            if ($this->input('application_type') === 'top_up' && (float) $this->input('increment_amount') <= 0) {
                $validator->errors()->add('increment_amount', 'An increment amount is required for top-up applications.');
            }
            if (in_array($this->input('application_type'), ['refinance', 'top_up'], true) && (float) $this->input('existing_loan_balance') <= 0) {
                $validator->errors()->add('existing_loan_balance', 'The existing outstanding loan balance is required for refinance and top-up applications.');
            }
        });
    }
}
