<?php

namespace App\Http\Requests;

use App\Models\LoanProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanApplicationRequest extends FormRequest
{
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
            'application_type' => ['nullable', Rule::in(['main', 'refinance', 'top_up'])],
            'requested_amount' => ['required', 'numeric', 'min:'.($product?->minimum_amount ?? 0), 'max:'.($product?->maximum_amount ?? PHP_INT_MAX)],
            'duration_months' => ['required', 'integer', 'min:'.($product?->minimum_duration_months ?? 1), 'max:'.($product?->maximum_duration_months ?? 120)],
            'loan_purpose' => ['nullable', 'string'],
            'business_summary' => ['nullable', 'string', 'max:5000'],
            'assessment' => ['nullable', 'array'],
            'assessment.core_business_income' => ['nullable', 'numeric', 'min:0'],
            'assessment.other_income' => ['nullable', 'numeric', 'min:0'],
            'assessment.business_expenses' => ['nullable', 'numeric', 'min:0'],
            'assessment.household_expenses' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
