<?php

namespace App\Http\Controllers\Web;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
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
        $isManagement = $request->user()->can('view-management-dashboard');
        $managementSummary = $isManagement
            ? $this->managementSummary($loans, $applications, $activeLoans, $branchId)
            : null;
        $recentPayments = Payment::with(['member', 'loan'])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->latest('paid_at')->limit(8)->get();

        $charts = [
            'collectionsTrend' => $this->collectionsTrend($branchId),
            'loanStatus' => $this->loanStatusBreakdown($loans),
            'applicationStatus' => $this->applicationStatusBreakdown($applications),
            'branchPortfolio' => ($isManagement && ! $branchId) ? $this->branchPortfolioBreakdown() : null,
        ];

        $data = compact('branchId', 'recentPayments', 'managementSummary', 'charts') + [
            'branches' => Branch::where('status', true)->orderBy('branch_name')->get(),
            'activeMembers' => (clone $members)->where('status', 'active')->count(),
            'activeLoanCount' => (clone $activeLoans)->count(),
            'expected' => $expected,
            'collected' => $collected,
            'collectionRate' => $expected > 0 ? round($collected / $expected * 100, 1) : 0,
            'overdueLoans' => (clone $loans)->where('status', 'overdue')->count(),
            'pendingApplications' => (clone $applications)->whereNotIn('status', ['approved', 'rejected', 'disbursed', 'cancelled'])->count(),
        ];

        return view($isManagement ? 'admin.dashboard-management' : 'admin.dashboard', $data);
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

    private function collectionsTrend(?int $branchId): array
    {
        $end = today();
        $start = $end->copy()->subDays(13);

        $scopedLoanIds = Loan::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['active', 'overdue'])
            ->select('id');

        $expectedByDate = LoanInstallment::whereIn('loan_id', clone $scopedLoanIds)
            ->whereBetween('due_date', [$start, $end])
            ->selectRaw('due_date, SUM(total_due) as total')
            ->groupBy('due_date')
            ->pluck('total', 'due_date');

        $collectedByDate = Payment::whereIn('loan_id', clone $scopedLoanIds)
            ->where('status', 'posted')
            ->whereBetween('paid_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('DATE(paid_at) as pay_date, SUM(amount) as total')
            ->groupBy('pay_date')
            ->pluck('total', 'pay_date');

        $labels = [];
        $expected = [];
        $collected = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('d M');
            $expected[] = round((float) ($expectedByDate[$key] ?? 0), 2);
            $collected[] = round((float) ($collectedByDate[$key] ?? 0), 2);
        }

        return compact('labels', 'expected', 'collected');
    }

    private function loanStatusBreakdown($loans): array
    {
        $counts = (clone $loans)->select('status')->get()->countBy(fn ($loan) => $loan->status->value);

        $labels = [];
        $data = [];
        foreach (LoanStatus::cases() as $status) {
            $count = $counts[$status->value] ?? 0;
            if ($count === 0) {
                continue;
            }
            $labels[] = __(ucwords(str_replace('_', ' ', $status->value)));
            $data[] = $count;
        }

        return compact('labels', 'data');
    }

    private function applicationStatusBreakdown($applications): array
    {
        $counts = (clone $applications)->select('status')->get()->countBy(fn ($application) => $application->status->value);

        $labels = [];
        $data = [];
        foreach (ApplicationStatus::cases() as $status) {
            $count = $counts[$status->value] ?? 0;
            if ($count === 0) {
                continue;
            }
            $labels[] = __(ucwords(str_replace('_', ' ', $status->value)));
            $data[] = $count;
        }

        return compact('labels', 'data');
    }

    private function branchPortfolioBreakdown(): array
    {
        $totalsByBranch = Loan::query()
            ->whereIn('status', ['active', 'overdue'])
            ->selectRaw('branch_id, SUM(total_balance) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        $labels = [];
        $data = [];
        foreach (Branch::where('status', true)->orderBy('branch_name')->get() as $branch) {
            $total = (float) ($totalsByBranch[$branch->id] ?? 0);
            if ($total <= 0) {
                continue;
            }
            $labels[] = $branch->branch_name;
            $data[] = round($total, 2);
        }

        return compact('labels', 'data');
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

