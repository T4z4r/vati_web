<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $this->branchId($request);
        $from = $request->date('date_from') ?? today()->startOfMonth();
        $to = $request->date('date_to') ?? today();
        $loans = Loan::query()->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->when($request->group_id, fn ($q, $id) => $q->where('group_id', $id))->when($request->loan_product_id, fn ($q, $id) => $q->where('loan_product_id', $id));
        $active = (clone $loans)->whereIn('status', ['active', 'overdue']);
        $portfolio = (float) (clone $active)->sum('total_balance');
        $collections = (float) Payment::where('status', 'posted')->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])->sum('amount');
        $par = function (int $days) use ($active, $portfolio) {
            if (! $portfolio) {
                return 0;
            }$amount = (float) (clone $active)->whereHas('installments', fn ($q) => $q->whereDate('due_date', '<=', today()->subDays($days))->whereNotIn('status', ['paid', 'waived']))->sum('principal_balance');

            return round($amount / $portfolio * 100, 2);
        };

        return view('admin.reports.index', ['branches' => Branch::where('status', true)->get(), 'groups' => MemberGroup::where('status', true)->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->get(), 'products' => LoanProduct::where('status', true)->get(), 'branchId' => $branchId, 'from' => $from, 'to' => $to, 'activeMembers' => Member::where('status', 'active')->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->count(), 'applications' => LoanApplication::when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->count(), 'disbursements' => (clone $loans)->whereBetween('disbursement_date', [$from, $to])->sum('principal_amount'), 'collections' => $collections, 'portfolio' => $portfolio, 'par1' => $par(1), 'par7' => $par(7), 'par30' => $par(30), 'arrearsLoans' => (clone $active)->whereHas('installments', fn ($q) => $q->whereDate('due_date', '<', today())->whereNotIn('status', ['paid', 'waived']))->with(['member', 'group'])->orderByDesc('total_balance')->limit(20)->get()]);
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }
}
