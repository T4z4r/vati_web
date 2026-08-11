<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LoanProduct;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanProductController extends ApiController
{
    public function index(Request $request)
    {
        return LoanProduct::when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")))->paginate($this->perPage($request));
    }

    public function store(Request $request, NumberGeneratorService $numbers)
    {
        $product = LoanProduct::create([...$this->validated($request), 'code' => $numbers->loanProduct()]);

        return response()->json(['success' => true, 'message' => 'Loan product created.', 'data' => $product], 201);
    }

    public function show(LoanProduct $loanProduct)
    {
        return response()->json(['success' => true, 'data' => $loanProduct]);
    }

    public function update(Request $request, LoanProduct $loanProduct)
    {
        $loanProduct->update($this->validated($request, $loanProduct));

        return response()->json(['success' => true, 'data' => $loanProduct->refresh()]);
    }

    public function destroy(LoanProduct $loanProduct)
    {
        $loanProduct->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?LoanProduct $product = null): array
    {
        return $request->validate(['name' => ['required', 'max:150'], 'minimum_amount' => ['required', 'numeric', 'min:0'], 'maximum_amount' => ['required', 'numeric', 'gte:minimum_amount'], 'minimum_duration_months' => ['required', 'integer', 'min:1'], 'maximum_duration_months' => ['required', 'integer', 'gte:minimum_duration_months'], 'annual_interest_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'interest_method' => ['required', Rule::in(['flat', 'reducing_balance'])], 'repayment_frequency' => ['required', Rule::in(['weekly', 'monthly'])], 'security_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'], 'processing_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'], 'transaction_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'], 'membership_fee' => ['nullable', 'numeric', 'min:0'], 'vat_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'], 'required_group_witnesses' => ['sometimes', 'integer', 'min:0', 'max:20'], 'status' => ['sometimes', 'boolean']]);
    }
}
