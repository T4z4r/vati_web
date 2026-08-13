<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Loan;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends ApiController
{
    public function store(Request $request, Loan $loan, PaymentService $service)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'loan_installment_id' => ['nullable', 'integer', 'exists:loan_installments,id'], 'payment_method' => ['required', Rule::in(['cash', 'mpesa', 'airtel_money', 'mixx', 'halopesa', 'bank_transfer'])], 'idempotency_key' => ['nullable', 'string', 'max:100'], 'uuid' => ['nullable', 'uuid'], 'reference_number' => ['nullable', 'string', 'max:100'], 'external_reference' => ['nullable', 'string', 'max:100'], 'paid_at' => ['nullable', 'date'], 'device_id' => ['nullable', 'string', 'max:100'], 'client_created_at' => ['nullable', 'date'], 'remarks' => ['nullable', 'string']]);
        $payment = $service->post($loan, $request->user(), (float) $data['amount'], $data);

        return response()->json(['success' => true, 'message' => 'Payment posted successfully.', 'data' => $payment], 201);
    }

    public function reverse(Request $request, Payment $payment, PaymentService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5']]);

        return response()->json(['success' => true, 'message' => 'Payment reversed successfully.', 'data' => $service->reverse($payment, $request->user(), $data['reason'])]);
    }
}
