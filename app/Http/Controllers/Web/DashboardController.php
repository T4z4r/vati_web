<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\LoanSettlement;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $this->branchId($request);
        $members = Member::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $loans = Loan::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $applications = LoanApplication::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $activeLoans = (clone $loans)->whereIn('status', ['active', 'overdue']);
        $loanIds = (clone $activeLoans)->select('id');
        $expected = (float) LoanInstallment::whereIn('loan_id', clone $loanIds)->whereDate('due_date', today())->sum('total_due');
        $collected = (float) Payment::whereIn('loan_id', clone $loanIds)->where('status', 'posted')->whereDate('paid_at', today())->sum('amount');
        $managementSummary = $request->user()->can('view-management-dashboard')
            ? $this->managementSummary($loans, $applications, $activeLoans, $branchId)
            : null;
        $recentPayments = Payment::with(['member', 'loan'])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->latest('paid_at')->limit(8)->get();

        return view('admin.dashboard', compact('branchId', 'recentPayments', 'managementSummary') + [
            'branches' => Branch::where('status', true)->orderBy('branch_name')->get(),
            'activeMembers' => (clone $members)->where('status', 'active')->count(),
            'activeLoanCount' => (clone $activeLoans)->count(),
            'expected' => $expected,
            'collected' => $collected,
            'collectionRate' => $expected > 0 ? round($collected / $expected * 100, 1) : 0,
            'overdueLoans' => (clone $loans)->where('status', 'overdue')->count(),
            'pendingApplications' => (clone $applications)->whereNotIn('status', ['approved', 'rejected', 'disbursed', 'cancelled'])->count(),
        ]);
    }

    private function managementSummary($loans, $applications, $activeLoans, ?int $branchId): array
    {
        $allLoanIds = (clone $loans)->select('id');
        $postedPayments = Payment::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->where('status', 'posted');
        $postedPaymentIds = (clone $postedPayments)->select('id');
        $repaymentIncome = (float) PaymentAllocation::whereIn('payment_id', clone $postedPaymentIds)
            ->selectRaw('COALESCE(SUM(interest_amount + penalty_amount), 0) as total')->value('total');
        $repaymentLoss = (float) LoanInstallment::whereIn('loan_id', clone $allLoanIds)->sum('interest_exemption')
            + (float) LoanSettlement::whereIn('loan_id', clone $allLoanIds)->sum('interest_waived');

        return [
            'portfolio' => (float) (clone $activeLoans)->sum('total_balance'),
            'totalPayments' => (float) (clone $postedPayments)->sum('amount'),
            'totalPaymentCount' => (clone $postedPayments)->count(),
            'repaymentIncome' => $repaymentIncome,
            'repaymentLoss' => $repaymentLoss,
            'repaymentProfitLoss' => $repaymentIncome - $repaymentLoss,
            'totalDisbursements' => (float) (clone $loans)->whereNotNull('disbursement_date')->sum('principal_amount'),
            'totalApplications' => (clone $applications)->count(),
            'requestedForDisbursement' => (float) (clone $applications)->whereNotIn('status', ['rejected', 'cancelled'])->sum('requested_amount'),
        ];
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user->hasAnyRole(['super_admin', 'head_office_admin'])) {
            return $user->branch_id;
        }

        return $request->integer('branch_id') ?: null;
    }
}
