<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PaymentResource;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SystemSetting;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PaymentController extends ApiController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('view-payments'), 403, 'You are not allowed to view repayments.');

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['posted', 'reversed'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'mpesa', 'airtel_money', 'mixx', 'halopesa', 'bank_transfer'])],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'loan_id' => ['nullable', 'integer', 'exists:loans,id'],
            'group_id' => ['nullable', 'integer', 'exists:member_groups,id'],
            'loan_product_id' => ['nullable', 'integer', 'exists:loan_products,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'collected_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'gte:min_amount'],
            'sort' => ['nullable', Rule::in(['paid_at', 'amount', 'created_at', 'payment_number'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $query = Payment::with('member', 'loan', 'branch', 'collectedBy', 'allocations.installment')
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('payment_number', 'like', "%{$search}%")
                ->orWhere('reference_number', 'like', "%{$search}%")
                ->orWhere('external_reference', 'like', "%{$search}%")
                ->orWhereHas('member', fn ($m) => $m->where(fn ($m) => $m
                    ->where('membership_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
                ->orWhereHas('loan', fn ($l) => $l->where('loan_number', 'like', "%{$search}%"))))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($data['payment_method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($data['member_id'] ?? null, fn ($q, $v) => $q->where('member_id', $v))
            ->when($data['loan_id'] ?? null, fn ($q, $v) => $q->where('loan_id', $v))
            ->when($data['collected_by'] ?? null, fn ($q, $v) => $q->where('collected_by', $v))
            ->when($data['group_id'] ?? null, fn ($q, $v) => $q->whereHas('loan', fn ($l) => $l->where('group_id', $v)))
            ->when($data['loan_product_id'] ?? null, fn ($q, $v) => $q->whereHas('loan', fn ($l) => $l->where('loan_product_id', $v)))
            ->when($data['date_from'] ?? null, fn ($q, $v) => $q->where('paid_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($data['date_to'] ?? null, fn ($q, $v) => $q->where('paid_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($data['min_amount'] ?? null, fn ($q, $v) => $q->where('amount', '>=', $v))
            ->when($data['max_amount'] ?? null, fn ($q, $v) => $q->where('amount', '<=', $v));

        $branchId = $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($data['branch_id'] ?? null) : $user->branch_id;
        $query->when($branchId, fn ($q, $v) => $q->where('branch_id', $v));

        if ($user->hasRole('loan_officer') && ! $user->hasAnyRole(['super_admin', 'head_office_admin']) && (bool) SystemSetting::get('restrict_loan_officer_groups', true)) {
            $query->whereHas('member.group', fn ($group) => $group->where('loan_officer_id', $user->id));
        }

        return PaymentResource::collection($this->paginate($query, $request))->additional(['summary' => $this->summary(clone $query)]);
    }

    private function paginate($query, Request $request)
    {
        return $query->orderBy($request->input('sort', 'paid_at'), $request->input('direction', 'desc'))
            ->paginate($this->perPage($request));
    }

    private function summary($query): array
    {
        $allocations = PaymentAllocation::whereIn('payment_id', $query->select('payments.id'))
            ->selectRaw('COALESCE(SUM(principal_amount), 0) AS principal, COALESCE(SUM(interest_amount), 0) AS interest, COALESCE(SUM(penalty_amount), 0) AS penalty')
            ->first();

        return [
            'count' => (clone $query)->count(),
            'total_amount' => number_format((float) (clone $query)->sum('amount'), 2, '.', ''),
            'posted_amount' => number_format((float) (clone $query)->where('status', 'posted')->sum('amount'), 2, '.', ''),
            'reversed_amount' => number_format((float) (clone $query)->where('status', 'reversed')->sum('amount'), 2, '.', ''),
            'principal' => number_format((float) $allocations->principal, 2, '.', ''),
            'interest' => number_format((float) $allocations->interest, 2, '.', ''),
            'penalty' => number_format((float) $allocations->penalty, 2, '.', ''),
        ];
    }

    public function store(Request $request, Loan $loan, PaymentService $service)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'loan_installment_id' => ['nullable', 'integer', 'exists:loan_installments,id'], 'payment_method' => ['required', Rule::in(['cash', 'mpesa', 'airtel_money', 'mixx', 'halopesa', 'bank_transfer'])], 'idempotency_key' => ['nullable', 'string', 'max:100'], 'uuid' => ['nullable', 'uuid'], 'reference_number' => ['nullable', 'string', 'max:100'], 'external_reference' => ['nullable', 'string', 'max:100'], 'paid_at' => ['nullable', 'date'], 'device_id' => ['nullable', 'string', 'max:100'], 'client_created_at' => ['nullable', 'date'], 'remarks' => ['nullable', 'string']]);
        $payment = $service->post($loan, $request->user(), (float) $data['amount'], $data);

        return response()->json([
            'success' => true,
            'message' => 'Payment posted successfully.',
            'data' => $payment,
            'loan' => [
                'id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'status' => $loan->refresh()->status->value,
                'principal_balance' => (string) $loan->principal_balance,
                'interest_balance' => (string) $loan->interest_balance,
                'total_balance' => (string) $loan->total_balance,
            ],
        ], 201);
    }

    public function update(Request $request, Payment $payment, PaymentService $service)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string'],
        ]);

        $payment = $service->editAmount($payment, $request->user(), (float) $data['amount'], $data['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Payment amount edited successfully.',
            'data' => $payment,
            'loan' => [
                'id' => $payment->loan->id,
                'loan_number' => $payment->loan->loan_number,
                'status' => $payment->loan->refresh()->status->value,
                'principal_balance' => (string) $payment->loan->principal_balance,
                'interest_balance' => (string) $payment->loan->interest_balance,
                'total_balance' => (string) $payment->loan->total_balance,
            ],
        ]);
    }

    public function reverse(Request $request, Payment $payment, PaymentService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5']]);

        return response()->json(['success' => true, 'message' => 'Payment reversed successfully.', 'data' => $service->reverse($payment, $request->user(), $data['reason'])]);
    }
}
