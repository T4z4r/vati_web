<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\PaymentService;
use DomainException;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, Loan $loan, PaymentService $service)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'loan_installment_id' => ['nullable', 'integer', 'exists:loan_installments,id'], 'payment_method' => ['required', 'in:cash,mpesa,airtel_money,mixx,halopesa,bank_transfer'], 'reference_number' => ['nullable', 'max:100'], 'paid_at' => ['nullable', 'date'], 'remarks' => ['nullable', 'string']]);
        $data['idempotency_key'] = 'web-'.str()->uuid();
        try {
            $service->post($loan, $request->user(), (float) $data['amount'], $data);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

return back()->with('success', 'Repayment posted successfully.');
    }

    public function reverse(Request $request, Payment $payment, PaymentService $service)
    {
        $data = $request->validate(['reason' => ['required', 'min:5']]);
        try {
            $service->reverse($payment, $request->user(), $data['reason']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

return back()->with('success','Payment reversed and balances restored.');
    }
}
