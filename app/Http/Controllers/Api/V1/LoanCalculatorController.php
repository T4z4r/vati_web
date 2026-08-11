<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LoanProduct;
use App\Services\LoanCalculatorService;
use Illuminate\Http\Request;

class LoanCalculatorController extends ApiController
{
    public function calculate(Request $request, LoanCalculatorService $calculator)
    {
        $data = $request->validate(['loan_product_id' => ['required', 'exists:loan_products,id'], 'amount' => ['required', 'numeric', 'gt:0'], 'duration_months' => ['required', 'integer', 'gt:0']]);

        return response()->json(['success' => true, 'data' => $calculator->calculate(LoanProduct::findOrFail($data['loan_product_id']), (float) $data['amount'], $data['duration_months'])]);
    }
}
