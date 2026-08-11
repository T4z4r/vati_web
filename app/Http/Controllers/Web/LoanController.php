<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\DisbursementService;
use App\Services\SettlementService;
use DomainException;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $loans = Loan::with(['member', 'product', 'group'])->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('loan_number', 'like', "%{$v}%")->orWhereHas('member', fn ($m) => $m->where('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%"))))->latest()->paginate(20)->withQueryString();

        return view('admin.loans.index', compact('loans'));
    }

    public function show(Loan $loan)
    {
        return view('admin.loans.show', ['loan' => $loan->load(['member', 'product', 'group', 'application', 'installments', 'payments.allocations', 'disbursement'])]);
    }

    public function disburse(Request $request, Loan $loan, DisbursementService $service)
    {
        $data = $request->validate(['method' => ['required', 'in:cash,mpesa,airtel_money,mixx,halopesa,bank_transfer'], 'recipient_number' => ['nullable', 'max:30'], 'reference_number' => ['nullable', 'max:100'], 'disbursed_at' => ['nullable', 'date'], 'first_payment_date' => ['nullable', 'date']]);
        try {
            $service->disburse($loan, $request->user(), $data);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

return back()->with('success', 'Loan disbursed and repayment schedule generated.');
    }

    public function settle(Request $request, Loan $loan, SettlementService $service)
    {
        $data = $request->validate(['interest_waived' => ['nullable', 'numeric', 'min:0'], 'security_offset' => ['nullable', 'numeric', 'min:0'], 'cash_payment' => ['nullable', 'numeric', 'min:0'], 'security_refund' => ['nullable', 'numeric', 'min:0']]);
        try {
            $service->settle($loan, $request->user(), $data);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

return back()->with('success', 'Loan settled successfully.');
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }
}
