<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function index(Request $request)
    {
        $members = $this->branchScope(Member::query(), $request);
        $loans = $this->branchScope(Loan::query(), $request);
        $payments = $this->branchScope(Payment::query(), $request);
        $active = (clone $loans)->whereIn('status', ['active', 'overdue']);
        $expected = LoanInstallment::whereIn('loan_id', (clone $active)->select('id'))->whereDate('due_date', today())->sum('total_due');
        $collected = (clone $payments)->where('status', 'posted')->whereDate('paid_at', today())->sum('amount');
        $portfolio = (float) (clone $active)->sum('total_balance');
        $par30 = (float) (clone $active)->whereHas('installments', fn ($q) => $q->whereIn('status', ['overdue', 'partially_paid'])->whereDate('due_date', '<', today()->subDays(30)))->sum('principal_balance');

        return response()->json(['success' => true, 'data' => ['active_members' => (clone $members)->where('status', 'active')->count(), 'active_loans' => (clone $active)->count(), 'portfolio_balance' => $portfolio, 'todays_expected_collection' => (float) $expected, 'todays_collection' => (float) $collected, 'collection_rate' => $expected > 0 ? round($collected / $expected * 100, 2) : 0, 'overdue_loans' => (clone $loans)->where('status', 'overdue')->count(), 'par_30' => $portfolio > 0 ? round($par30 / $portfolio * 100, 2) : 0]]);
    }
}
