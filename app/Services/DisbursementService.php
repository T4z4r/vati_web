<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Exceptions\WorkflowConflictException;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DisbursementService
{
    public function __construct(private RepaymentScheduleService $schedule, private NotificationService $notifications, private SecurityAccountService $securityAccounts) {}

    public function disburse(Loan $loan, User $user, array $data): LoanDisbursement
    {
        return DB::transaction(function () use ($loan, $user, $data) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($loan->disbursement()->exists()) {
                throw new WorkflowConflictException('This loan has already been disbursed.');
            }
            if ($loan->status !== LoanStatus::PENDING_DISBURSEMENT || $loan->application->status !== ApplicationStatus::APPROVED) {
                throw new WorkflowConflictException('Only an approved, pending loan can be disbursed.');
            }
            if ($loan->application->cancellation()->exists()) {
                throw new DomainException('A cancelled application cannot be disbursed.');
            }

            $date = Carbon::parse($data['issued_date'] ?? $data['disbursed_at'] ?? now());
            $amount = $loan->calc_amount_receivable;
            if ($amount === null || (float) $amount <= 0 || (float) $amount > (float) $loan->principal_amount || $amount !== $loan->amount_receivable) {
                throw new WorkflowConflictException('The saved amount receivable is invalid or outdated. Review the loan before disbursement.');
            }
            // API callers must send an amount; internal/web callers may omit it.
            // Any supplied amount is checked against the locked, saved receivable.
            if (array_key_exists('amount', $data)) {
                Validator::make($data, ['amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2']])->validate();
                $parts = explode('.', (string) $data['amount'], 2);
                $submitted = (ltrim($parts[0], '+0') ?: '0').'.'.str_pad($parts[1] ?? '', 2, '0');
                if ($submitted !== $amount) {
                    throw new WorkflowConflictException('The submitted amount does not match the saved amount receivable. Refresh the loan and try again.');
                }
            }
            $firstPayment = $date->copy()->addWeek();
            $disbursement = $loan->disbursement()->create([
                'amount' => $amount,
                'method' => $data['method'],
                'recipient_number' => $data['recipient_number'] ?? null,
                'bank_account' => $data['bank_account'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'provider_reference' => $data['provider_reference'] ?? null,
                'disbursed_at' => $date,
                'processed_by' => $user->id,
                'approved_by' => $user->id,
                'status' => 'completed',
            ]);
            $securityAmount = (float) ($loan->calc_security_amount ?? 0);
            if ($securityAmount < 0) {
                throw new DomainException('The saved loan security amount cannot be negative.');
            }
            if ($securityAmount > 0) {
                $this->securityAccounts->transact($loan->member, $user, 'deposit', $securityAmount, [
                    'loan_id' => $loan->id,
                    'transaction_date' => $date,
                    'remarks' => 'Security withheld on disbursement of loan '.$loan->loan_number,
                ]);
            }
            $loan->update([
                'status' => LoanStatus::ACTIVE,
                'calc_amount_receivable' => $amount,
                'disbursement_date' => $date,
                'first_payment_date' => $firstPayment,
                'maturity_date' => $loan->product->repayment_frequency === 'weekly' ? $firstPayment->copy()->addWeeks($loan->number_of_installments - 1) : $firstPayment->copy()->addMonths($loan->number_of_installments - 1),
            ]);
            $loan->application->update(['status' => ApplicationStatus::DISBURSED]);
            $this->schedule->generate($loan->fresh('product'), $firstPayment);
            activity()->causedBy($user)->performedOn($loan)->withProperties(['amount' => $amount, 'principal' => $loan->principal_amount, 'charges' => $loan->calc_charges ?? $loan->total_fees_and_vat, 'security' => $loan->calc_security_amount])->log('Loan disbursed');
            $this->notifications->send(
                $this->notifications->applicationOriginators($loan->application),
                'loan_disbursed',
                'Loan disbursed',
                "Loan {$loan->loan_number} has been disbursed.",
                'loan',
                $loan->id
            );

            return $disbursement;
        });
    }
}
