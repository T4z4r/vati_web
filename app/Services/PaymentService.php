<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private NumberGeneratorService $numbers, private NotificationService $notifications) {}

    public function post(Loan $loan, User $user, float $amount, array $data): Payment
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new DomainException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($loan, $user, $amount, $data) {
            if (! empty($data['idempotency_key'])) {
                $existing = Payment::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing->load('allocations');
                }
            }

            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if (! in_array($loan->status, [LoanStatus::ACTIVE, LoanStatus::OVERDUE], true)) {
                throw new DomainException('Payments can only be posted to active or overdue loans.');
            }
            $outstandingBalance = round((float) $loan->total_balance, 2);
            if ($amount - $outstandingBalance > 0.009) {
                throw new DomainException('Payment amount cannot exceed the outstanding loan balance.');
            }
            $amount = min($amount, $outstandingBalance);

            $payment = Payment::create([
                'uuid' => $data['uuid'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'payment_number' => $this->numbers->payment(),
                'member_id' => $loan->member_id,
                'loan_id' => $loan->id,
                'branch_id' => $loan->branch_id,
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'collected_by' => $user->id,
                'device_id' => $data['device_id'] ?? null,
                'client_created_at' => $data['client_created_at'] ?? null,
                'server_received_at' => now(),
                'sync_status' => 'synced',
                'remarks' => $data['remarks'] ?? null,
            ]);

            $remaining = round($amount, 2);
            foreach ($loan->installments()->whereIn('status', ['upcoming', 'due', 'partially_paid', 'overdue'])->orderBy('installment_number')->lockForUpdate()->get() as $installment) {
                if ($remaining <= 0) {
                    break;
                }
                $interestDue = max(0, (float) $installment->interest_due - (float) $installment->interest_paid - (float) $installment->interest_exemption);
                $interest = min($remaining, $interestDue);
                $remaining = round($remaining - $interest, 2);
                $principalDue = max(0, (float) $installment->principal_due - (float) $installment->principal_paid);
                $principal = min($remaining, $principalDue);
                $remaining = round($remaining - $principal, 2);

                if ($interest + $principal > 0) {
                    $payment->allocations()->create([
                        'loan_installment_id' => $installment->id,
                        'principal_amount' => $principal,
                        'interest_amount' => $interest,
                    ]);
                    $installment->principal_paid = round((float) $installment->principal_paid + $principal, 2);
                    $installment->interest_paid = round((float) $installment->interest_paid + $interest, 2);
                    $installment->total_paid = round((float) $installment->total_paid + $principal + $interest, 2);
                    $effectiveDue = (float) $installment->total_due - (float) $installment->interest_exemption;
                    $installment->status = (float) $installment->total_paid + 0.009 >= $effectiveDue ? 'paid' : 'partially_paid';
                    $installment->save();
                }
            }

            $interestPaid = round((float) $payment->allocations()->sum('interest_amount'), 2);
            $principalPaid = round((float) $payment->allocations()->sum('principal_amount'), 2);

            // Keep accepting repayments when an older or manually adjusted loan has a
            // schedule that differs from its authoritative loan balances.
            if ($remaining > 0.009) {
                $residualInterest = min($remaining, max(0, round((float) $loan->interest_balance - $interestPaid, 2)));
                $remaining = round($remaining - $residualInterest, 2);
                $residualPrincipal = min($remaining, max(0, round((float) $loan->principal_balance - $principalPaid, 2)));
                $remaining = round($remaining - $residualPrincipal, 2);

                if ($residualInterest + $residualPrincipal > 0) {
                    $payment->allocations()->create([
                        'loan_installment_id' => null,
                        'principal_amount' => $residualPrincipal,
                        'interest_amount' => $residualInterest,
                    ]);
                    $interestPaid = round($interestPaid + $residualInterest, 2);
                    $principalPaid = round($principalPaid + $residualPrincipal, 2);
                }
            }

            if ($remaining > 0.009 || abs($amount - $principalPaid - $interestPaid) > 0.009) {
                throw new DomainException('The repayment could not be fully allocated to the outstanding loan balance.');
            }

            $loan->principal_balance = max(0, round((float) $loan->principal_balance - $principalPaid, 2));
            $loan->interest_balance = max(0, round((float) $loan->interest_balance - $interestPaid, 2));
            $loan->total_balance = round($loan->principal_balance + $loan->interest_balance, 2);
            if ($loan->total_balance <= 0.009) {
                $loan->principal_balance = 0;
                $loan->interest_balance = 0;
                $loan->total_balance = 0;
                $loan->status = LoanStatus::SETTLED;
            }
            $loan->save();

            activity()->causedBy($user)->performedOn($loan)->withProperties(['amount' => $amount, 'payment_number' => $payment->payment_number])->log('Loan repayment posted');
            $this->notifications->send(
                $this->notifications->applicationOriginators($loan->application),
                'payment_posted',
                'Payment posted',
                "Payment {$payment->payment_number} was posted for loan {$loan->loan_number}.",
                'payment',
                $payment->id
            );

            return $payment->load('allocations');
        });
    }

    public function reverse(Payment $payment, User $user, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $user, $reason) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'posted') {
                throw new DomainException('Only posted payments can be reversed.');
            }
            $loan = Loan::query()->lockForUpdate()->findOrFail($payment->loan_id);

            foreach ($payment->allocations()->with('installment')->get() as $allocation) {
                if ($allocation->installment) {
                    $installment = $allocation->installment;
                    $installment->principal_paid = max(0, round((float) $installment->principal_paid - (float) $allocation->principal_amount, 2));
                    $installment->interest_paid = max(0, round((float) $installment->interest_paid - (float) $allocation->interest_amount, 2));
                    $installment->total_paid = round($installment->principal_paid + $installment->interest_paid, 2);
                    $installment->status = $installment->total_paid > 0 ? 'partially_paid' : ($installment->due_date->isPast() ? 'overdue' : 'upcoming');
                    $installment->save();
                }
            }

            $loan->principal_balance = round((float) $loan->principal_balance + (float) $payment->allocations()->sum('principal_amount'), 2);
            $loan->interest_balance = round((float) $loan->interest_balance + (float) $payment->allocations()->sum('interest_amount'), 2);
            $loan->total_balance = round($loan->principal_balance + $loan->interest_balance, 2);
            $loan->status = $loan->installments()
                ->whereDate('due_date', '<', today())
                ->whereNotIn('status', ['paid', 'waived'])
                ->exists() ? LoanStatus::OVERDUE : LoanStatus::ACTIVE;
            $loan->save();
            $payment->update(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason]);
            activity()->causedBy($user)->performedOn($payment)->withProperties(['reason' => $reason])->log('Loan repayment reversed');
            $this->notifications->send(
                $this->notifications->applicationOriginators($loan->application),
                'payment_reversed',
                'Payment reversed',
                "Payment {$payment->payment_number} was reversed for loan {$loan->loan_number}.",
                'payment',
                $payment->id
            );

            return $payment->refresh();
        });
    }
}
