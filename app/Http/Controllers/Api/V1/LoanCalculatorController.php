<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LoanProduct;
use App\Services\LoanCalculatorService;
use Illuminate\Http\Request;

class LoanCalculatorController extends ApiController
{
    public function calculate(Request $request, LoanCalculatorService $calculator)
    {
        $data = $request->validate([
            'loan_product_id' => ['required', 'exists:loan_products,id'],
            'principal' => ['required', 'numeric', 'gt:0'],
            'duration_months' => ['required', 'integer', 'gt:0'],
        ]);

        $product = LoanProduct::findOrFail($data['loan_product_id']);
        abort_unless($product->status, 422, 'The selected loan product is not active.');

        $result = $calculator->calculate($product, (float) $data['principal'], $data['duration_months']);

        return response()->json(['success' => true, 'data' => collect($result)->map(fn ($v) => number_format($v, 2, '.', ''))->all()]);
    }
}
