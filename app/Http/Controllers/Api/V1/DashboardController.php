<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatus;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\LoanSettlement;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\PortfolioAnalyticsService;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(private readonly PortfolioAnalyticsService $portfolio) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $user = $request->user();
        $branchId = $this->portfolio->authorizedBranch($user, $filters['branch_id'] ?? null);
        $role = $user->getRoleNames()->first() ?? 'user';
        $fromDate = ($filters['from'] ?? null) ? \Carbon\Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $toDate = ($filters['to'] ?? null) ? \Carbon\Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        $data = [
            'role' => $role,
            'as_of' => now()->toIso8601String(),
        ];

        $assigned = LoanApplication::query()->where('assigned_credit_officer_id', $user->id);
        if ($branchId) {
            $assigned->where('branch_id', $branchId);
        }

        $pendingReviewStatuses = [ApplicationStatus::SUBMITTED, ApplicationStatus::CREDIT_REVIEW, ApplicationStatus::RETURNED];
        $pendingReview = (clone $assigned)->whereIn('status', $pendingReviewStatuses)->count();

        $todayStart = now()->startOfDay();
        $newAssignments = (clone $assigned)->where('status', ApplicationStatus::SUBMITTED)->where('updated_at', '>=', $todayStart)->count();

        $reviewedToday = (clone $assigned)->whereIn('status', [ApplicationStatus::RECOMMENDED, ApplicationStatus::APPROVED, ApplicationStatus::REJECTED])->where('updated_at', '>=', $todayStart)->count();

        $returnedCases = (clone $assigned)->where('status', ApplicationStatus::RETURNED)->count();

        $highRiskCases = (clone $assigned)->whereIn('risk_level', ['high', 'critical'])->whereIn('status', [ApplicationStatus::CREDIT_REVIEW, ApplicationStatus::RECOMMENDED])->count();

        $portfolio = $this->portfolio->summary($user, $branchId, $filters['from'] ?? null, $filters['to'] ?? null);

        $data['credit_officer'] = [
            'pending_credit_review' => $pendingReview,
            'new_assignments' => $newAssignments,
            'reviewed_today' => $reviewedToday,
            'returned_cases' => $returnedCases,
            'high_risk_cases' => $highRiskCases,
            'daily_target' => (int) config('vati.credit_daily_target', 10),
            'daily_completed' => $reviewedToday,
            'gross_loan_portfolio' => $portfolio['gross_loan_portfolio'],
            'active_loans' => $portfolio['active_loans'],
            'collection_rate' => (float) $portfolio['collection_rate'],
            'portfolio_at_risk' => (float) $portfolio['portfolio_at_risk'],
            'performing_amount' => $portfolio['performing_amount'],
            'at_risk_amount' => $portfolio['at_risk_amount'],
            'overdue_amount' => $portfolio['overdue_amount'],
            'total_issued_amount' => $portfolio['total_issued_amount'],
        ];

        $data['admin'] = [
            'gross_loan_portfolio' => $portfolio['gross_loan_portfolio'],
            'active_loans' => $portfolio['active_loans'],
            'collection_rate' => (float) $portfolio['collection_rate'],
            'portfolio_at_risk' => (float) $portfolio['portfolio_at_risk'],
            'performing_amount' => $portfolio['performing_amount'],
            'at_risk_amount' => $portfolio['at_risk_amount'],
            'overdue_amount' => $portfolio['overdue_amount'],
            'total_issued_amount' => $portfolio['total_issued_amount'],
        ];

        $loans = Loan::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $applications = LoanApplication::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $activeLoans = (clone $loans)->whereIn('status', ['active', 'overdue']);
        $data['management'] = $this->managementSummary($loans, $applications, $activeLoans, $branchId);

        return response()->json(['success' => true, 'data' => $data]);
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
            'total_payments' => (float) (clone $postedPayments)->sum('amount'),
            'total_payment_count' => (clone $postedPayments)->count(),
            'repayment_income' => $repaymentIncome,
            'repayment_loss' => $repaymentLoss,
            'repayment_profit_loss' => $repaymentIncome - $repaymentLoss,
            'total_disbursements' => (float) (clone $loans)->whereNotNull('disbursement_date')->sum('principal_amount'),
            'total_applications' => (clone $applications)->count(),
            'requested_for_disbursement' => (float) (clone $applications)->whereNotIn('status', ['rejected', 'cancelled'])->sum('requested_amount'),
        ];
    }
}
