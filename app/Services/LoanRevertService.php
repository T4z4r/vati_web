<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class LoanRevertService
{
    public function revert(Loan $loan, User $user): array
    {
        return DB::transaction(function () use ($loan, $user) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $application = $loan->application;

            if (! $application) {
                throw new DomainException('This loan has no linked application to revert to.');
            }

            $this->assertRevertible($loan, $application);

            $this->purgeDependants($loan);

            $loanNumber = $loan->loan_number;
            $loan->delete();

            $application->update(['status' => ApplicationStatus::APPROVED]);

            activity()
                ->causedBy($user)
                ->performedOn($application)
                ->withProperties(['reverted_loan_number' => $loanNumber])
                ->log('Loan reverted to approved loan application');

            return [
                'loan_number' => $loanNumber,
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'message' => "Loan {$loanNumber} deleted and application {$application->application_number} reverted to approved.",
            ];
        });
    }

    private function assertRevertible(Loan $loan, LoanApplication $application): void
    {
        if (! in_array($application->status->value, [ApplicationStatus::APPROVED->value, ApplicationStatus::DISBURSED->value], true)) {
            throw new DomainException('Only an approved or disbursed application can be reverted alongside its loan.');
        }

        if ($loan->payments()->exists()) {
            throw new DomainException('This loan has recorded payments and cannot be reverted.');
        }

        if ($loan->installmentRecords()->exists()) {
            throw new DomainException('This loan has recorded installment payments and cannot be reverted.');
        }

        if ($loan->securityTransactions()->exists() || DB::table('security_transactions')->where('loan_id', $loan->id)->exists()) {
            throw new DomainException('This loan has security activity and cannot be reverted.');
        }

        if ($loan->defaultNotices()->exists() || $loan->clearance()->exists() || $loan->settlement()->exists()) {
            throw new DomainException('This loan has compliance or settlement activity and cannot be reverted.');
        }

        if (DB::table('loan_refinancings')->where('old_loan_id', $loan->id)->orWhere('new_loan_id', $loan->id)->exists()) {
            throw new DomainException('This loan is part of a refinancing and cannot be reverted.');
        }
    }

    private function purgeDependants(Loan $loan): void
    {
        $loan->defaultNotices()->delete();
        $loan->clearance()?->delete();
        $loan->settlement()?->delete();
        $loan->disbursement()?->delete();
        $loan->cycles()->delete();
        $loan->installmentRecords()->delete();
        $loan->installments()->delete();
    }
}