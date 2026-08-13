<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LoanResource;
use App\Models\Loan;
use Illuminate\Http\Request;

class LoanController extends ApiController
{
    public function index(Request $request)
    {
        $query = $this->branchScope(Loan::with('member', 'product'), $request)->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->member_id, fn ($q, $v) => $q->where('member_id', $v));

        return LoanResource::collection($query->paginate($this->perPage($request)));
    }

    public function show(Loan $loan)
    {
        return response()->json(['success' => true, 'data' => new LoanResource($loan->load([
            'member.branch.manager', 'member.group.loanOfficer', 'member.kyc', 'product', 'group',
            'application.guarantors', 'application.groupWitnesses.member', 'cycles', 'installments',
            'installmentRecords.collector', 'payments.allocations', 'securityTransactions.collectedBy',
            'securityTransactions.approvedBy', 'disbursement', 'settlement', 'clearance', 'defaultNotices',
        ]))]);
    }

    public function schedule(Loan $loan)
    {
        $installments = $loan->installments()->with('allocations.payment')->orderBy('installment_number')->get();

        return response()->json(['success' => true, 'data' => $installments, 'summary' => [
            'loan_id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'status' => $loan->status->value,
            'total_repayment' => (string) $loan->total_repayment,
            'total_paid' => number_format($installments->sum('total_paid'), 2, '.', ''),
            'outstanding_balance' => (string) $loan->total_balance,
            'paid_installments' => $installments->where('status', 'paid')->count(),
            'total_installments' => $installments->count(),
        ]]);
    }
}
