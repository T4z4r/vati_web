<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanSettlement;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function __construct(private NumberGeneratorService $numbers) {}

    public function settle(Loan $loan, User $user, array $data): LoanSettlement
    {
        return DB::transaction(function () use ($loan, $user, $data) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if (! in_array($loan->status, [LoanStatus::ACTIVE, LoanStatus::OVERDUE], true)) {
                throw new DomainException('This loan cannot be settled.');
            }
            $waived = min((float) ($data['interest_waived'] ?? 0), (float) $loan->interest_balance);
            $security = max(0, (float) ($data['security_offset'] ?? 0));
            $cash = max(0, (float) ($data['cash_payment'] ?? 0));
            $final = round((float) $loan->total_balance - $waived - $security - $cash, 2);
            if (abs($final) > 0.009) {
                throw new DomainException('Settlement inputs must clear the full outstanding balance.');
            }

            $settlement = LoanSettlement::create([
                'settlement_number' => $this->numbers->settlement(),
                'loan_id' => $loan->id,
                'settlement_date' => $data['settlement_date'] ?? today(),
                'principal_outstanding' => $loan->principal_balance,
                'interest_outstanding' => $loan->interest_balance,
                'interest_waived' => $waived,
                'security_offset' => $security,
                'cash_payment' => $cash,
                'security_refund' => $data['security_refund'] ?? 0,
                'final_balance' => 0,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            $loan->update(['principal_balance' => 0, 'interest_balance' => 0, 'total_balance' => 0, 'status' => LoanStatus::SETTLED]);
            $loan->clearance()->updateOrCreate(['loan_id' => $loan->id], [
                'loan_outstanding_amount' => 0,
                'security_offset' => $security,
                'cash_collection' => $cash,
                'security_refund' => $data['security_refund'] ?? 0,
                'status' => 'pending',
            ]);
            activity()->causedBy($user)->performedOn($loan)->log('Loan settled');

            return $settlement;
        });
    }
}
