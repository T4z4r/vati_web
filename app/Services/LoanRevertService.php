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
    public function revert(Loan $loan, User $user, bool $force = false): array
    {
        return DB::transaction(function () use ($loan, $user, $force) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $application = $loan->application;

            if (! $application) {
                throw new DomainException('This loan has no linked application to revert to.');
            }

            $this->assertRevertible($loan, $application, $force);

            $deleted = $this->purgeDependants($loan, $force);

            $loanNumber = $loan->loan_number;
            $loan->delete();

            $application->update(['status' => ApplicationStatus::REVERTED]);

            activity()
                ->causedBy($user)
                ->performedOn($application)
                ->withProperties(['reverted_loan_number' => $loanNumber, 'forced' => $force, 'deleted' => $deleted])
                ->log($force ? 'Loan force reverted to editable loan application' : 'Loan reverted to editable loan application');

            return [
                'loan_number' => $loanNumber,
                'application_id' => $application->id,
                'application_number' => $application->application_number,
                'message' => "Loan {$loanNumber} deleted and application {$application->application_number} reverted to editable status.",
                'forced' => $force,
                'deleted' => $deleted,
            ];
        });
    }

    private function assertRevertible(Loan $loan, LoanApplication $application, bool $force): void
    {
        if (! in_array($application->status->value, [ApplicationStatus::APPROVED->value, ApplicationStatus::DISBURSED->value], true)) {
            throw new DomainException('Only an approved or disbursed application can be reverted alongside its loan.');
        }

        if ($force) {
            return;
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

    private function purgeDependants(Loan $loan, bool $force): array
    {
        $loanId = (int) $loan->id;
        $deleted = [];

        if ($force) {
            $deleted['payment_allocations'] = DB::table('payment_allocations')
                ->whereIn('payment_id', DB::table('payments')->where('loan_id', $loanId)->select('id'))
                ->delete();
            $deleted['payments'] = DB::table('payments')->where('loan_id', $loanId)->delete();
            $deleted['payment_transactions'] = DB::table('payment_transactions')->where('loan_id', $loanId)->delete();
            $deleted['security_transactions'] = $this->deleteSecurityLedgerForLoan($loan);
            $deleted['loan_refinancings'] = DB::table('loan_refinancings')
                ->where('old_loan_id', $loanId)
                ->orWhere('new_loan_id', $loanId)
                ->delete();
        }

        $deleted['loan_default_notices'] = DB::table('loan_default_notices')->where('loan_id', $loanId)->delete();
        $deleted['loan_clearances'] = DB::table('loan_clearances')->where('loan_id', $loanId)->delete();
        $deleted['loan_settlements'] = DB::table('loan_settlements')->where('loan_id', $loanId)->delete();
        $deleted['loan_disbursements'] = DB::table('loan_disbursements')->where('loan_id', $loanId)->delete();
        $deleted['loan_installment_records'] = DB::table('loan_installment_records')->where('loan_id', $loanId)->delete();
        $deleted['loan_security_transactions'] = DB::table('loan_security_transactions')->where('loan_id', $loanId)->delete();
        $deleted['loan_cycles'] = DB::table('loan_cycles')->where('loan_id', $loanId)->delete();
        $deleted['loan_installments'] = DB::table('loan_installments')->where('loan_id', $loanId)->delete();
        $deleted['activity_log'] = DB::table('activity_log')
            ->where('subject_type', Loan::class)
            ->where('subject_id', $loanId)
            ->delete();

        return $deleted;
    }

    private function deleteSecurityLedgerForLoan(Loan $loan): int
    {
        $accountIds = DB::table('security_transactions')->where('loan_id', $loan->id)->pluck('member_security_account_id');
        $deleted = DB::table('security_transactions')->where('loan_id', $loan->id)->delete();

        foreach ($accountIds->unique() as $accountId) {
            $balance = 0.0;
            $transactions = DB::table('security_transactions')
                ->where('member_security_account_id', $accountId)
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get(['id', 'transaction_type', 'amount']);

            foreach ($transactions as $transaction) {
                $before = $balance;
                $amount = (float) $transaction->amount;
                $credit = in_array($transaction->transaction_type, ['deposit', 'adjustment'], true);
                $balance = round($balance + ($credit ? $amount : -$amount), 2);
                DB::table('security_transactions')->where('id', $transaction->id)->update([
                    'balance_before' => $before,
                    'balance_after' => $balance,
                ]);
            }

            DB::table('member_security_accounts')->where('id', $accountId)->update(['balance' => $balance]);
        }

        return $deleted;
    }
}
