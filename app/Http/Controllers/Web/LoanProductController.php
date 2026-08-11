<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanProductController extends Controller
{
    public function index()
    {
        return view('admin.loan-products.index', ['products' => LoanProduct::withCount('applications')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.loan-products.form', ['product' => new LoanProduct]);
    }

    public function store(Request $request, NumberGeneratorService $numbers)
    {
        $product = LoanProduct::create([...$this->data($request), 'code' => $numbers->loanProduct()]);

        return redirect()->route('admin.loan-products.show', $product)->with('success', 'Loan product created.');
    }

    public function show(LoanProduct $loanProduct)
    {
        return view('admin.loan-products.show', ['product' => $loanProduct]);
    }

    public function edit(LoanProduct $loanProduct)
    {
        return view('admin.loan-products.form', ['product' => $loanProduct]);
    }

    public function update(Request $request, LoanProduct $loanProduct)
    {
        $loanProduct->update($this->data($request, $loanProduct));

        return redirect()->route('admin.loan-products.show', $loanProduct)->with('success', 'Loan product updated.');
    }

    private function data(Request $request, ?LoanProduct $product = null): array
    {
        return $request->validate(['name' => ['required', 'max:150'], 'minimum_amount' => ['required', 'numeric', 'min:0'], 'maximum_amount' => ['required', 'numeric', 'gte:minimum_amount'], 'minimum_duration_months' => ['required', 'integer', 'min:1'], 'maximum_duration_months' => ['required', 'integer', 'gte:minimum_duration_months'], 'annual_interest_rate' => ['required', 'numeric', 'min:0', 'max:100'], 'interest_method' => ['required', Rule::in(['flat', 'reducing_balance'])], 'repayment_frequency' => ['required', Rule::in(['weekly', 'monthly'])], 'security_percentage' => ['required', 'numeric', 'min:0', 'max:100'], 'processing_fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'], 'transaction_fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'], 'membership_fee' => ['required', 'numeric', 'min:0'], 'vat_percentage' => ['required', 'numeric', 'min:0', 'max:100'], 'required_group_witnesses' => ['required', 'integer', 'min:0', 'max:20'], 'status' => ['nullable', 'boolean']]);
    }
}
