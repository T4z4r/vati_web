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
    public function __construct(private LoanCalculatorService $calculator, private NumberGeneratorService $numbers, private ApplicationComplianceService $compliance, private NotificationService $notifications) {}

    public function decide(LoanApplication $application, User $user, string $decision, ?string $remarks = null): LoanApplication
    {
        return DB::transaction(function () use ($application, $user, $decision, $remarks) {
            $application = LoanApplication::query()->lockForUpdate()->findOrFail($application->id);
            $from = $application->status->value;

            if (! in_array($from, ['submitted', 'lo_review', 'abm_review', 'bm_review', 'credit_review', 'recommended'], true)) {
                throw new DomainException('This application cannot be decided in its current state.');
            }

            $to = $decision === 'approved' ? ApplicationStatus::APPROVED : ApplicationStatus::REJECTED;
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
                $approvedAmount = (float) ($application->recommended_amount ?: $application->requested_amount);
                $approvedDuration = (int) ($application->recommended_duration_months ?: $application->duration_months);
                $figures = $this->calculator->calculate($application->product, $approvedAmount, $approvedDuration);
                $installments = $application->product->repayment_frequency === 'weekly'
                    ? max(1, (int) round($approvedDuration * 52 / 12))
                    : $approvedDuration;
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
                    'processing_fee' => $figures['processing_fee'],
                    'transaction_charges' => $figures['transaction_fee'],
                    'other_charges' => $figures['membership_fee'],
                    'total_fees_and_vat' => $figures['charges'],
                    'calc_security_amount' => $figures['security_amount'],
                    'calc_amount_receivable' => $figures['amount_receivable'],
                ]);
            }

            activity()->causedBy($user)->performedOn($application)->withProperties(['from' => $from, 'to' => $to->value])->log("Loan application {$decision}");
            $this->notifications->send(
                $this->notifications->applicationOriginators($application),
                'loan_application_'.$decision,
                'Loan application '.ucfirst($decision),
                "Application {$application->application_number} was {$decision}.",
                'loan_application',
                $application->id
            );

            return $application->refresh()->load('loan');
        });
    }
}
