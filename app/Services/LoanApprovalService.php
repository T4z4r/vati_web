<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApproval;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class LoanApprovalService
{
    public function __construct(private LoanCalculatorService $calculator, private NumberGeneratorService $numbers, private ApplicationComplianceService $compliance) {}

    public function decide(LoanApplication $application, User $user, string $decision, ?string $remarks = null): LoanApplication
    {
        return DB::transaction(function () use ($application, $user, $decision, $remarks) {
            $application = LoanApplication::query()->lockForUpdate()->findOrFail($application->id);
            $from = $application->status->value;

            if (! in_array($from, ['submitted', 'lo_review', 'abm_review', 'bm_review', 'credit_review'], true)) {
                throw new DomainException('This application cannot be decided in its current state.');
            }

            $to = $decision === 'approved' ? ApplicationStatus::APPROVED : ApplicationStatus::REJECTED;
            if ($to === ApplicationStatus::APPROVED) {
                $this->compliance->assertReadyForApproval($application);
                $application->loadMissing(['member.activeGroupMembership', 'group', 'product']);
                if ($application->member->status !== 'active' || ! $application->group->status || $application->member->activeGroupMembership?->group_id !== $application->group_id) {
                    throw new DomainException('The borrower must still be an active member of the originating group.');
                }
                $confirmedWitnesses = $application->groupWitnesses()->whereNotNull('confirmed_at')->count();
                if ($confirmedWitnesses < $application->product->required_group_witnesses) {
                    throw new DomainException("At least {$application->product->required_group_witnesses} confirmed group witnesses are required.");
                }
            }
            LoanApproval::create([
                'loan_application_id' => $application->id,
                'user_id' => $user->id,
                'role' => $user->getRoleNames()->first() ?? 'user',
                'decision' => $decision,
                'from_status' => $from,
                'to_status' => $to->value,
                'remarks' => $remarks,
                'acted_at' => now(),
            ]);
            $application->update(['status' => $to]);

            if ($to === ApplicationStatus::APPROVED && ! $application->loan()->exists()) {
                $figures = $this->calculator->calculate($application->product, (float) $application->requested_amount, $application->duration_months);
                $installments = $application->product->repayment_frequency === 'weekly'
                    ? max(1, (int) round($application->duration_months * 52 / 12))
                    : $application->duration_months;
                Loan::create([
                    'loan_number' => $this->numbers->loan(),
                    'loan_application_id' => $application->id,
                    'member_id' => $application->member_id,
                    'group_id' => $application->group_id,
                    'loan_product_id' => $application->loan_product_id,
                    'branch_id' => $application->branch_id,
                    'principal_amount' => $figures['principal'],
                    'interest_amount' => $figures['interest'],
                    'total_repayment' => $figures['total_repayment'],
                    'principal_balance' => $figures['principal'],
                    'interest_balance' => $figures['interest'],
                    'total_balance' => $figures['total_repayment'],
                    'number_of_installments' => $installments,
                    'installment_amount' => round($figures['total_repayment'] / $installments, 2),
                ]);
            }

            activity()->causedBy($user)->performedOn($application)->withProperties(['from' => $from, 'to' => $to->value])->log("Loan application {$decision}");

            return $application->refresh()->load('loan');
        });
    }
}
