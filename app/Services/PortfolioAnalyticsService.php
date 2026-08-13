<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PortfolioAnalyticsService
{
    public function summary(User $user, ?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        $branchId = $this->authorizedBranch($user, $branchId);
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $loans = Loan::query()->whereIn('status', ['active', 'overdue']);
        $payments = Payment::query()->where('status', 'posted');
        if ($branchId) {
            $loans->where('branch_id', $branchId);
            $payments->where('branch_id', $branchId);
        }

        $loanIds = (clone $loans)->select('id');
        $expected = (float) LoanInstallment::query()->whereIn('loan_id', $loanIds)
            ->whereBetween('due_date', [$fromDate->toDateString(), $toDate->toDateString()])->sum('total_due');
        $collected = (float) (clone $payments)->whereBetween('paid_at', [$fromDate, $toDate])->sum('amount');
        $portfolio = (float) (clone $loans)->sum('total_balance');
        $atRisk = (float) (clone $loans)->whereHas('installments', fn (Builder $query) => $query
            ->whereIn('status', ['overdue', 'partially_paid'])->whereDate('due_date', '<=', $toDate->copy()->subDays(30)))
            ->sum('principal_balance');
        $overdue = (float) LoanInstallment::query()->whereIn('loan_id', $loanIds)
            ->whereIn('status', ['overdue', 'partially_paid'])->whereDate('due_date', '<=', $toDate)
            ->selectRaw('COALESCE(SUM(total_due - total_paid - interest_exemption), 0) as total')
            ->value('total');

        return [
            'branch_id' => $branchId,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'gross_loan_portfolio' => $this->money($portfolio),
            'active_loans' => (clone $loans)->count(),
            'expected_collection' => $this->money($expected),
            'collected_amount' => $this->money($collected),
            'collection_rate' => $this->percent($expected > 0 ? $collected / $expected * 100 : 0),
            'performing_amount' => $this->money(max(0, $portfolio - $atRisk)),
            'at_risk_amount' => $this->money($atRisk),
            'portfolio_at_risk' => $this->percent($portfolio > 0 ? $atRisk / $portfolio * 100 : 0),
            'overdue_amount' => $this->money($overdue),
        ];
    }

    public function branches(User $user, ?string $from, ?string $to): array
    {
        $query = Branch::query()->where('status', true)->orderBy('branch_name');
        $forcedBranch = $this->authorizedBranch($user, null);
        if ($forcedBranch) {
            $query->whereKey($forcedBranch);
        }

        return $query->get()->map(fn (Branch $branch) => [
            'id' => $branch->id,
            'name' => $branch->branch_name,
            'code' => $branch->branch_code,
            ...$this->summary($user, $branch->id, $from, $to),
        ])->all();
    }

    public function authorizedBranch(User $user, ?int $requested): ?int
    {
        if (! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id) {
            abort_if($requested && $requested !== $user->branch_id, 403, 'You cannot access another branch.');

            return $user->branch_id;
        }

        if ($requested) {
            abort_unless(Branch::query()->whereKey($requested)->exists(), 422, 'The selected branch is invalid.');
        }

        return $requested;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function percent(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
