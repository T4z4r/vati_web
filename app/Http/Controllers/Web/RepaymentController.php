<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Services\ExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidFormatException;

class RepaymentController extends Controller
{
    private const STATUS_TABS = [
        'posted' => 'Posted',
        'reversed' => 'Reversed',
    ];

    private const METHODS = [
        'cash' => 'Cash',
        'mpesa' => 'M-Pesa',
        'airtel_money' => 'Airtel Money',
        'mixx' => 'Mixx',
        'halopesa' => 'HaloPesa',
        'bank_transfer' => 'Bank',
    ];

    public function index(Request $request)
    {
        return view('admin.repayments.index', [
            'repayments' => $this->filteredQuery($request)
                ->with(['member', 'loan', 'collectedBy', 'allocations.installment'])
                ->orderByDesc('paid_at')
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'statusTabs' => $this->statusTabs($request),
            'activeStatus' => $this->activeStatus($request) ?? '',
            'stats' => $this->listingStats($request),
            'methods' => self::METHODS,
            'activeMethod' => $this->activeMethod($request) ?? '',
        ]);
    }

    public function expected(Request $request)
    {
        $collectionDate = $this->collectionDate($request);
        $query = $this->expectedQuery($request, $collectionDate);

        return view('admin.repayments.expected', [
            'installments' => (clone $query)
                ->with(['loan.member', 'loan.group'])
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'collectionDate' => $collectionDate,
            'stats' => $this->expectedStats(clone $query),
        ]);
    }

    private function listingStats(Request $request): array
    {
        $posted = $this->filteredQuery($request, false)->where('payments.status', 'posted');
        $today = $posted->clone()->whereBetween('paid_at', [now()->startOfDay(), now()->endOfDay()]);
        $week = $posted->clone()->whereBetween('paid_at', [now()->startOfWeek(), now()->endOfWeek()]);
        $pending = $this->pendingQuery($request);

        return [
            'today' => (int) $today->toBase()->count(),
            'todayAmount' => (float) ($today->toBase()->sum('payments.amount') ?? 0),
            'thisWeek' => (int) $week->toBase()->count(),
            'thisWeekAmount' => (float) ($week->toBase()->sum('payments.amount') ?? 0),
            'pending' => (int) $pending->toBase()->count(),
            'pendingAmount' => (float) ($pending->toBase()->sum('outstanding_balance') ?? 0),
            'total' => (int) $posted->toBase()->count(),
            'totalAmount' => (float) ($posted->toBase()->sum('payments.amount') ?? 0),
        ];
    }

    private function filteredQuery(Request $request, bool $applyStatus = true): Builder
    {
        $status = $this->activeStatus($request);
        $from = $this->dateFilter($request, 'from');
        $to = $this->dateFilter($request, 'to');
        $search = $this->stringQuery($request, 'search');

        return $this->baseQuery($request)
            ->when($applyStatus ? $status : null, fn ($q, $v) => $q->where('payments.status', $v))
            ->when($this->activeMethod($request), fn ($q, $v) => $q->where('payments.payment_method', $v))
            ->when($from, fn ($q, $v) => $q->where('paid_at', '>=', $v->copy()->startOfDay()))
            ->when($to, fn ($q, $v) => $q->where('paid_at', '<=', $v->copy()->endOfDay()))
            ->when($search, fn ($q, $v) => $q->where(fn ($q) => $q
                ->where('payment_number', 'like', "%{$v}%")
                ->orWhere('reference_number', 'like', "%{$v}%")
                ->orWhereHas('member', fn ($m) => $m->where(fn ($m) => $m
                    ->where('membership_number', 'like', "%{$v}%")
                    ->orWhere('first_name', 'like', "%{$v}%")
                    ->orWhere('last_name', 'like', "%{$v}%")))
                ->orWhereHas('loan', fn ($l) => $l->where('loan_number', 'like', "%{$v}%"))));
    }

    private function dateFilter(Request $request, string $key): ?Carbon
    {
        $value = $request->query($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    private function baseQuery(Request $request): Builder
    {
        return Payment::query()->when($this->branchId($request), fn ($q, $id) => $q->where('payments.branch_id', $id));
    }

    private function pendingQuery(Request $request): Builder
    {
        return LoanInstallment::query()
            ->whereDate('due_date', '<=', now()->toDateString())
            ->where('outstanding_balance', '>', 0)
            ->when($this->branchId($request), fn ($q, $id) => $q->whereHas('loan', fn ($l) => $l->where('branch_id', $id)));
    }

    private function expectedQuery(Request $request, Carbon $collectionDate): Builder
    {
        $search = $this->stringQuery($request, 'search');

        return LoanInstallment::query()
            ->whereDate('due_date', $collectionDate->toDateString())
            ->whereHas('loan', fn ($loan) => $loan
                ->whereIn('status', ['active', 'overdue'])
                ->when($this->branchId($request), fn ($loan, $id) => $loan->where('branch_id', $id)))
            ->when($search, fn ($query, $value) => $query->whereHas('loan', fn ($loan) => $loan
                ->where('loan_number', 'like', "%{$value}%")
                ->orWhereHas('member', fn ($member) => $member->where(fn ($member) => $member
                    ->where('membership_number', 'like', "%{$value}%")
                    ->orWhere('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%")))
                ->orWhereHas('group', fn ($group) => $group->where('group_name', 'like', "%{$value}%"))));
    }

    private function expectedStats(Builder $query): array
    {
        $rows = $query->toBase()
            ->selectRaw("
                COUNT(*) as total,
                COALESCE(SUM(total_due), 0) as expected_amount,
                COALESCE(SUM(total_paid), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN total_due - total_paid - interest_exemption > 0
                    THEN total_due - total_paid - interest_exemption ELSE 0 END), 0) as outstanding_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) as paid_count,
                COALESCE(SUM(CASE WHEN status = 'partially_paid' THEN 1 ELSE 0 END), 0) as partial_count,
                COALESCE(SUM(CASE WHEN status NOT IN ('paid', 'waived', 'partially_paid') THEN 1 ELSE 0 END), 0) as pending_count
            ")
            ->first();

        return [
            'total' => (int) ($rows->total ?? 0),
            'expectedAmount' => (float) ($rows->expected_amount ?? 0),
            'paidAmount' => (float) ($rows->paid_amount ?? 0),
            'outstandingAmount' => (float) ($rows->outstanding_amount ?? 0),
            'paid' => (int) ($rows->paid_count ?? 0),
            'partial' => (int) ($rows->partial_count ?? 0),
            'pending' => (int) ($rows->pending_count ?? 0),
        ];
    }

    private function collectionDate(Request $request): Carbon
    {
        $timezone = config('app.timezone', 'Africa/Dar_es_Salaam');

        return $request->filled('collection_date')
            ? Carbon::parse($request->query('collection_date'), $timezone)->startOfDay()
            : Carbon::now($timezone)->startOfDay();
    }

    private function activeStatus(Request $request): ?string
    {
        $status = $this->stringQuery($request, 'status');

        return array_key_exists($status, self::STATUS_TABS) ? $status : null;
    }

    private function activeMethod(Request $request): ?string
    {
        $method = $this->stringQuery($request, 'payment_method');

        return array_key_exists($method, self::METHODS) ? $method : null;
    }

    private function stringQuery(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    private function statusTabs(Request $request): array
    {
        $counts = $this->filteredQuery($request, false)
            ->toBase()
            ->select('payments.status', DB::raw('count(*) as total'))
            ->groupBy('payments.status')
            ->pluck('total', 'status');

        $tabs = [['key' => '', 'label' => 'All', 'count' => (int) $counts->sum()]];

        foreach (self::STATUS_TABS as $key => $label) {
            $tabs[] = ['key' => $key, 'label' => $label, 'count' => (int) $counts->get($key, 0)];
        }

        return $tabs;
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 20);

        return in_array($perPage, [10, 20, 25, 50, 100], true) ? $perPage : 20;
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }

    public function export(Request $request, ExportService $exporter, string $format)
    {
        $rows = $this->filteredQuery($request)
            ->with(['member', 'loan', 'collectedBy', 'allocations.installment'])
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (Payment $payment) => [
                'payment_number' => $payment->payment_number,
                'paid_at' => $payment->paid_at?->format('d M Y H:i'),
                'member' => trim("{$payment->member?->first_name} {$payment->member?->last_name}"),
                'membership_number' => $payment->member?->membership_number,
                'loan_number' => $payment->loan?->loan_number,
                'method' => str_replace('_', ' ', $payment->payment_method),
                'principal' => 'TZS '.number_format($this->allocationTotal($payment, 'principal_amount')),
                'interest' => 'TZS '.number_format($this->allocationTotal($payment, 'interest_amount')),
                'penalty' => 'TZS '.number_format($this->allocationTotal($payment, 'penalty_amount')),
                'amount' => 'TZS '.number_format((float) $payment->amount),
                'collected_by' => $payment->collectedBy?->name,
                'status' => $payment->status,
            ])->values()->all();

        return $exporter->export(
            'VATI Repayments List',
            ['Receipt', 'Date', 'Member', 'Membership No', 'Loan No', 'Method', 'Principal', 'Interest', 'Penalty', 'Amount', 'Collected By', 'Status'],
            $rows,
            'VATI-repayments-'.now()->format('Ymd-His'),
            $format
        );
    }

    private function allocationTotal(Payment $payment, string $column): float
    {
        return (float) $payment->allocations->sum($column);
    }
}
