<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanCycle;
use App\Models\LoanInstallmentRecord;
use App\Models\LoanSecurityTransaction;
use App\Services\DisbursementService;
use App\Services\SettlementService;
use DomainException;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $loans = Loan::with(['member', 'product', 'group'])->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('loan_number', 'like', "%{$v}%")->orWhereHas('member', fn ($m) => $m->where('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%"))))->latest()->paginate(20)->withQueryString();

        return view('admin.loans.index', compact('loans'));
    }

    public function show(Loan $loan)
    {
        return view('admin.loans.show', ['loan' => $loan->load(['member', 'product', 'group', 'application', 'installments', 'payments.allocations', 'disbursement', 'settlement', 'defaultNotices', 'clearance'])]);
    }

    public function disburse(Request $request, Loan $loan, DisbursementService $service)
    {
        $data = $request->validate(['method' => ['required', 'in:cash,mpesa,airtel_money,mixx,halopesa,bank_transfer'], 'recipient_number' => ['nullable', 'max:30'], 'reference_number' => ['nullable', 'max:100'], 'disbursed_at' => ['nullable', 'date'], 'first_payment_date' => ['nullable', 'date']]);
        try {
            $service->disburse($loan, $request->user(), $data);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Loan disbursed and repayment schedule generated.');
    }

    public function settle(Request $request, Loan $loan, SettlementService $service)
    {
        $data = $request->validate(['interest_waived' => ['nullable', 'numeric', 'min:0'], 'security_offset' => ['nullable', 'numeric', 'min:0'], 'cash_payment' => ['nullable', 'numeric', 'min:0'], 'security_refund' => ['nullable', 'numeric', 'min:0']]);
        try {
            $service->settle($loan, $request->user(), $data);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Loan settled successfully.');
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }

    /**
     * Record a new loan cycle
     */
    public function storeCycle(Request $request, Loan $loan)
    {
        $this->authorize('update', $loan);

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'cycle_type' => 'required|in:main,refinancing',
            'principal_amount' => 'required|numeric|min:0',
            'adjusted_principal_amount' => 'nullable|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0',
            'disbursement_date' => 'required|date',
            'first_payment_date' => 'required|date|after_or_equal:disbursement_date',
            'admission_fee' => 'required|numeric|min:0',
            'processing_fee' => 'required|numeric|min:0',
            'transaction_charges' => 'required|numeric|min:0',
            'weekly_installment' => 'required|numeric|min:0',
            'total_installments' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $validated['is_main_cycle'] = $validated['cycle_type'] === 'main';
        $validated['is_refinancing_cycle'] = $validated['cycle_type'] === 'refinancing';
        $validated['status'] = 'active';
        $validated['adjusted_principal_amount'] = $validated['adjusted_principal_amount'] ?? $validated['principal_amount'];

        // Calculate totals
        $validated['total_fees_and_vat'] = $validated['admission_fee'] + $validated['processing_fee'] + $validated['transaction_charges'];
        $validated['total_with_interest'] = $validated['adjusted_principal_amount'] + ($validated['weekly_installment'] * $validated['total_installments']);

        $cycle = $loan->cycles()->create($validated);

        // Generate installment records
        $this->generateInstallmentRecords($loan, $cycle);

        activity()
            ->performedOn($cycle)
            ->withProperties(['loan_id' => $loan->id])
            ->log('Loan cycle created');

        return redirect()->back()->with('success', 'Loan cycle recorded successfully');
    }

    /**
     * Record installment payment
     */
    public function recordInstallment(Request $request, Loan $loan)
    {
        $this->authorize('update', $loan);

        $validated = $request->validate([
            'loan_cycle_id' => 'required|exists:loan_cycles,id',
            'installment_number' => 'required|integer|min:1',
            'payment_date' => 'required|date',
            'principal_amount' => 'required|numeric|min:0',
            'interest_amount' => 'required|numeric|min:0',
            'interest_exemption' => 'nullable|numeric|min:0',
            'outstanding_balance' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        $validated['loan_id'] = $loan->id;
        $validated['is_paid'] = true;
        $validated['actual_payment_date'] = now();
        $validated['collector_id'] = auth()->id();
        $validated['total_amount'] = $validated['principal_amount'] + $validated['interest_amount'];

        $record = LoanInstallmentRecord::updateOrCreate(
            [
                'loan_id' => $loan->id,
                'loan_cycle_id' => $validated['loan_cycle_id'],
                'installment_number' => $validated['installment_number'],
            ],
            $validated
        );

        activity()
            ->performedOn($record)
            ->withProperties(['loan_id' => $loan->id])
            ->log('Installment payment recorded');

        return redirect()->back()->with('success', 'Installment recorded successfully');
    }

    /**
     * Record security transaction
     */
    public function recordSecurityTransaction(Request $request, Loan $loan)
    {
        $this->authorize('update', $loan);

        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'security_amount' => 'nullable|numeric|min:0',
            'withdrawal_amount' => 'nullable|numeric|min:0',
            'collector_signature' => 'nullable|string',
            'branch_manager_signature' => 'nullable|string',
        ]);

        // Ensure at least one of security_amount or withdrawal_amount is provided
        if (($validated['security_amount'] ?? 0) == 0 && ($validated['withdrawal_amount'] ?? 0) == 0) {
            return redirect()->back()->withErrors(['message' => 'Either security amount or withdrawal amount must be provided']);
        }

        $validated['loan_id'] = $loan->id;
        $validated['collected_by'] = auth()->id();

        // Get the last balance or start with 0
        $lastTransaction = $loan->securityTransactions()->latest('transaction_date')->first();
        $lastBalance = $lastTransaction?->balance ?? 0;

        // Calculate new balance
        $security = $validated['security_amount'] ?? 0;
        $withdrawal = $validated['withdrawal_amount'] ?? 0;
        $validated['balance'] = $lastBalance + $security - $withdrawal;

        $transaction = $loan->securityTransactions()->create($validated);

        activity()
            ->performedOn($transaction)
            ->withProperties(['loan_id' => $loan->id])
            ->log('Security transaction recorded');

        return redirect()->back()->with('success', 'Security transaction recorded successfully');
    }

    /**
     * Generate installment records for a loan cycle
     */
    private function generateInstallmentRecords(Loan $loan, LoanCycle $cycle)
    {
        $paymentDate = \Carbon\Carbon::parse($cycle->first_payment_date ?? $cycle->disbursement_date);
        $totalInstallments = $cycle->total_installments;
        $weeklyAmount = $cycle->weekly_installment;
        $totalPrincipal = $cycle->adjusted_principal_amount ?? $cycle->principal_amount;

        // Calculate principal per installment
        $principalPerInstallment = $totalPrincipal / $totalInstallments;

        for ($i = 1; $i <= $totalInstallments; $i++) {
            $calculatedPaymentDate = $paymentDate->copy()->addWeeks($i - 1);
            $outstandingBalance = $totalPrincipal - ($principalPerInstallment * $i);

            LoanInstallmentRecord::updateOrCreate(
                [
                    'loan_id' => $loan->id,
                    'loan_cycle_id' => $cycle->id,
                    'installment_number' => $i,
                ],
                [
                    'payment_date' => $calculatedPaymentDate,
                    'principal_amount' => $principalPerInstallment,
                    'interest_amount' => $weeklyAmount - $principalPerInstallment,
                    'total_amount' => $weeklyAmount,
                    'outstanding_balance' => $outstandingBalance,
                    'is_paid' => false,
                ]
            );
        }
    }
}
