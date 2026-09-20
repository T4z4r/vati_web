<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Loan;
use App\Http\Resources\LoanResource;
use App\Services\DisbursementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanDisbursementController extends ApiController
{
    public function store(Request $request, Loan $loan, DisbursementService $service)
    {
        $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2']]);
        $data = $request->validate(['method' => ['required', Rule::in(['cash', 'mpesa', 'airtel_money', 'mixx', 'halopesa', 'bank_transfer'])], 'recipient_number' => ['nullable', 'string', 'max:30'], 'bank_account' => ['nullable', 'string', 'max:100'], 'reference_number' => ['nullable', 'string', 'max:100'], 'provider_reference' => ['nullable', 'string', 'max:100'], 'disbursed_at' => ['nullable', 'date'], 'first_payment_date' => ['nullable', 'date', 'after_or_equal:disbursed_at']]);

        $data['amount'] = $request->input('amount');
        $service->disburse($loan, $request->user(), $data);

        return response()->json(['success' => true, 'message' => 'Loan disbursed successfully.', 'data' => new LoanResource($loan->fresh(['member', 'product', 'disbursement', 'installments']))], 201);
    }
}
