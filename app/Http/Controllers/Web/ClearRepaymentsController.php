<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payment;
use App\Services\PaymentService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClearRepaymentsController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
    }

    private function query(array $data)
    {
        return Payment::where('status', 'posted')->whereDate('paid_at', $data['date'])
            ->when($data['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id));
    }

    public function index(Request $request)
    {
        $data = $request->has('date') ? $this->filters($request) : null;
        $query = $data ? $this->query($data) : null;

        return view('admin.repayments.clear', [
            'filters' => $data,
            'count' => $query ? (clone $query)->count() : 0,
            'amount' => $query ? (clone $query)->sum('amount') : 0,
            'payments' => $query ? $query->with(['loan', 'member'])->orderBy('id')->paginate(25)->withQueryString() : null,
            'branches' => Branch::orderBy('branch_name')->get(),
        ]);
    }

    public function store(Request $request, PaymentService $service)
    {
        $data = $this->filters($request);
        $request->validate(['reason' => 'required|string|min:5|max:1000']);
        try {
            $count = DB::transaction(function () use ($data, $request, $service) {
                $payments = $this->query($data)->orderBy('loan_id')->orderByDesc('id')->lockForUpdate()->get();
                foreach ($payments as $payment) {
                    $service->reverse($payment, $request->user(), $request->string('reason')->toString());
                }
                activity()->causedBy($request->user())->withProperties($data + [
                    'payment_ids' => $payments->modelKeys(), 'reason' => $request->input('reason'),
                ])->log('Repayments cleared by payment date');

                return $payments->count();
            });
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.repayments.clear', $data)
            ->with('success', "$count repayments cleared. Loan and installment balances restored; receipts retained as reversed.");
    }
}
