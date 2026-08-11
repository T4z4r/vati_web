<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LoanApplicationResource;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\MemberGroup;
use App\Models\Payment;
use Illuminate\Http\Request;

class GroupPortfolioController extends ApiController
{
    public function dashboard(MemberGroup $group)
    {
        $activeLoans = $group->loans()->whereIn('status', ['active', 'overdue']);
        $portfolio = (float) (clone $activeLoans)->sum('total_balance');
        $loanIds = (clone $activeLoans)->select('id');
        $weekStart = today()->startOfWeek();
        $weekEnd = today()->endOfWeek();
        $expected = (float) LoanInstallment::whereIn('loan_id', clone $loanIds)->whereBetween('due_date', [$weekStart, $weekEnd])->sum('total_due');
        $actual = (float) Payment::whereIn('loan_id', clone $loanIds)->where('status', 'posted')->whereBetween('paid_at', [$weekStart, $weekEnd])->sum('amount');
        $arrears = (float) LoanInstallment::whereIn('loan_id', clone $loanIds)->whereDate('due_date', '<', today())->whereNotIn('status', ['paid', 'waived'])->get()->sum(fn ($item) => max(0, (float) $item->total_due - (float) $item->total_paid - (float) $item->interest_exemption));

        return response()->json(['success' => true, 'data' => [
            'group_id' => $group->id,
            'total_members' => $group->members()->count(),
            'active_members' => $group->members()->where('status', 'active')->count(),
            'members_with_active_loans' => (clone $activeLoans)->distinct('member_id')->count('member_id'),
            'outstanding_portfolio' => $portfolio,
            'expected_weekly_collection' => $expected,
            'actual_weekly_collection' => $actual,
            'outstanding_weekly_collection' => max(0, round($expected - $actual, 2)),
            'collection_rate' => $expected > 0 ? round($actual / $expected * 100, 2) : 0,
            'arrears' => round($arrears, 2),
            'par_1' => $this->par($group, $portfolio, 1),
            'par_7' => $this->par($group, $portfolio, 7),
            'par_30' => $this->par($group, $portfolio, 30),
        ]]);
    }

    public function loans(Request $request, MemberGroup $group) { return LoanResource::collection($group->loans()->with(['member', 'product'])->latest()->paginate($this->perPage($request))); }

    public function applications(Request $request, MemberGroup $group) { return LoanApplicationResource::collection($group->loanApplications()->with(['member', 'product'])->latest()->paginate($this->perPage($request))); }

    public function collections(Request $request, MemberGroup $group) { return $group->collections()->with('meeting')->latest('collection_date')->paginate($this->perPage($request)); }

    public function meetings(Request $request, MemberGroup $group) { return $group->meetings()->latest('meeting_date')->paginate($this->perPage($request)); }

    private function par(MemberGroup $group, float $portfolio, int $days): float
    {
        if ($portfolio <= 0) return 0;
        $atRisk = (float) Loan::where('group_id', $group->id)->whereIn('status', ['active', 'overdue'])
            ->whereHas('installments', fn ($query) => $query->whereDate('due_date', '<=', today()->subDays($days))->whereNotIn('status', ['paid', 'waived']))
            ->sum('principal_balance');

        return round($atRisk / $portfolio * 100, 2);
    }
}
