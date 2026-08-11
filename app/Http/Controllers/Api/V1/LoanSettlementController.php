<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Loan;
use App\Services\SettlementService;
use Illuminate\Http\Request;

class LoanSettlementController extends ApiController
{
    public function store(Request $request, Loan $loan, SettlementService $service)
    {
        $data = $request->validate(['settlement_date' => ['nullable', 'date'], 'interest_waived' => ['nullable', 'numeric', 'min:0'], 'security_offset' => ['nullable', 'numeric', 'min:0'], 'cash_payment' => ['nullable', 'numeric', 'min:0'], 'security_refund' => ['nullable', 'numeric', 'min:0']]);

        return response()->json(['success' => true, 'message' => 'Loan settled successfully.', 'data' => $service->settle($loan, $request->user(), $data)], 201);
    }
}
