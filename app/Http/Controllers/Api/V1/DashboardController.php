<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatus;
use App\Models\CreditReview;
use App\Models\Loan;
use App\Models\LoanApplication;
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

        $data['credit_officer'] = [
            'pending_credit_review' => $pendingReview,
            'new_assignments' => $newAssignments,
            'reviewed_today' => $reviewedToday,
            'returned_cases' => $returnedCases,
            'high_risk_cases' => $highRiskCases,
            'daily_target' => (int) config('vati.credit_daily_target', 10),
            'daily_completed' => $reviewedToday,
        ];

        $portfolio = $this->portfolio->summary($user, $branchId, $filters['from'] ?? null, $filters['to'] ?? null);

        $data['admin'] = [
            'gross_loan_portfolio' => $portfolio['gross_loan_portfolio'],
            'active_loans' => $portfolio['active_loans'],
            'collection_rate' => (float) $portfolio['collection_rate'],
            'portfolio_at_risk' => (float) $portfolio['portfolio_at_risk'],
            'performing_amount' => $portfolio['performing_amount'],
            'at_risk_amount' => $portfolio['at_risk_amount'],
            'overdue_amount' => $portfolio['overdue_amount'],
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }
}
