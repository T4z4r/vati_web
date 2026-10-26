<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LoanResource;
use App\Models\Loan;
use Illuminate\Http\Request;

class LoanController extends ApiController
{
    public function index(Request $request)
    {
        $query = Loan::with('member', 'product')
            ->withOverdueStatus()
            ->when($request->status, function ($query, $status) {
                if ($status === 'overdue') {
                    return $query->overdue();
                }

                $query->where('status', $status);
                $query->whereDoesntHave('installments', fn ($installments) => $installments->unpaidPastDue());

                return $query;
            })
            ->when($request->member_id, fn ($query, $memberId) => $query->where('member_id', $memberId));

        return LoanResource::collection($query->latest()->paginate($this->perPage($request)));
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
            'status' => $loan->effective_status->value,
            'total_repayment' => (string) $loan->total_repayment,
            'total_paid' => number_format($installments->sum('total_paid'), 2, '.', ''),
            'outstanding_balance' => (string) $loan->total_balance,
            'paid_installments' => $installments->where('status', 'paid')->count(),
            'total_installments' => $installments->count(),
        ]]);
    }
}
