<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class DisbursementService
{
    public function __construct(private RepaymentScheduleService $schedule, private NotificationService $notifications) {}

    public function disburse(Loan $loan, User $user, array $data): LoanDisbursement
    {
        return DB::transaction(function () use ($loan, $user, $data) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($loan->status !== LoanStatus::PENDING_DISBURSEMENT || $loan->application->status !== ApplicationStatus::APPROVED) {
                throw new DomainException('Only an approved, pending loan can be disbursed.');
            }
            if ($loan->application->cancellation()->exists()) {
                throw new DomainException('A cancelled application cannot be disbursed.');
            }

            $date = Carbon::parse($data['disbursed_at'] ?? now());
            $amount = $loan->amount_receivable;
            if ((float) $amount < 0 || (float) $amount > (float) $loan->principal_amount) {
                throw new DomainException('Loan fees and security produce an invalid disbursement amount.');
            }
            $firstPayment = Carbon::parse($data['first_payment_date'] ?? ($loan->product->repayment_frequency === 'weekly' ? $date->copy()->addWeek() : $date->copy()->addMonth()));
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
