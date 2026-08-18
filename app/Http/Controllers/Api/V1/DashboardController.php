<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatus;
use App\Models\CreditReview;
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

        $data = [
            'as_of' => now()->toIso8601String(),
        ];

        $assigned = LoanApplication::query()->where('assigned_credit_officer_id', $user->id);
        if ($branchId) {
            $assigned->where('branch_id', $branchId);
        }
        $priority = (clone $assigned)->whereIn('status', [ApplicationStatus::SUBMITTED, ApplicationStatus::CREDIT_REVIEW])
            ->with(['member:id,first_name,last_name,membership_number', 'product:id,name'])
            ->oldest('submitted_at')->limit(10)->get()->map(fn (LoanApplication $application) => [
                'id' => $application->id,
                'application_number' => $application->application_number,
                'member_name' => trim($application->member->first_name.' '.$application->member->last_name),
                'product' => $application->product->name,
                'requested_amount' => (string) $application->requested_amount,
                'status' => $application->status->value,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
            ]);

        $data['credit_officer'] = [
            'pending_credit_review' => (clone $assigned)->whereIn('status', [ApplicationStatus::SUBMITTED, ApplicationStatus::CREDIT_REVIEW])->count(),
            'new_assignments_today' => (clone $assigned)->whereDate('updated_at', today())->count(),
            'reviewed_today' => CreditReview::query()->where('reviewed_by', $user->id)->whereDate('reviewed_at', today())->count(),
            'returned_cases' => (clone $assigned)->where('status', ApplicationStatus::RETURNED)->count(),
            'high_risk_cases' => (clone $assigned)->where('risk_level', 'high')->whereIn('status', [ApplicationStatus::CREDIT_REVIEW, ApplicationStatus::RECOMMENDED])->count(),
            'daily_target' => (int) config('vati.credit_daily_target', 10),
            'priority_applications' => $priority,
        ];

        $applications = LoanApplication::query();
        if ($branchId) {
            $applications->where('branch_id', $branchId);
        }
        $data['administration'] = [
            'pending_final_approval' => (clone $applications)->where('status', ApplicationStatus::RECOMMENDED)->count(),
            'returned_applications' => (clone $applications)->where('status', ApplicationStatus::RETURNED)->count(),
            'portfolio' => $this->portfolio->summary($user, $branchId, $filters['from'] ?? null, $filters['to'] ?? null),
        ];

        $data['management_financial_summary'] = $this->managementSummary($branchId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    private function managementSummary(?int $branchId): array
    {
        $loans = Loan::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $applications = LoanApplication::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
        $activeLoans = (clone $loans)->whereIn('status', ['active', 'overdue']);
        $postedPayments = Payment::query()->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'posted');
        $repaymentIncome = (float) PaymentAllocation::whereIn('payment_id', (clone $postedPayments)->select('id'))
            ->selectRaw('COALESCE(SUM(interest_amount + penalty_amount), 0) as total')->value('total');
        $repaymentLoss = (float) LoanInstallment::whereIn('loan_id', (clone $loans)->select('id'))->sum('interest_exemption')
            + (float) LoanSettlement::whereIn('loan_id', (clone $loans)->select('id'))->sum('interest_waived');

        return [
            'total_loan_portfolio' => $this->money((float) $activeLoans->sum('total_balance')),
            'total_posted_payments' => $this->money((float) (clone $postedPayments)->sum('amount')),
            'posted_payment_count' => (clone $postedPayments)->count(),
            'repayment_income' => $this->money($repaymentIncome),
            'repayment_loss' => $this->money($repaymentLoss),
            'repayment_profit_or_loss' => $this->money($repaymentIncome - $repaymentLoss),
            'total_loan_disbursement' => $this->money((float) (clone $loans)->whereNotNull('disbursement_date')->sum('principal_amount')),
            'total_loan_applications' => (clone $applications)->count(),
            'amount_requested_for_disbursement' => $this->money((float) (clone $applications)->whereNotIn('status', ['rejected', 'cancelled'])->sum('requested_amount')),
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
