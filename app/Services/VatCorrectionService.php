<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use DomainException;
use Illuminate\Support\Facades\DB;

class VatCorrectionService
{
    public function __construct(
        private LoanCalculatorService $calculator,
        private RepaymentScheduleService $schedule
    ) {}

    /**
     * Recompute the fee/VAT/security/repayment figures a saved loan row should hold,
     * using the product's current rates and the loan's own total repayment and term.
     */
    private function loanFigures(Loan $loan): array
    {
        $product = $loan->product;
        $principal = round((float) $loan->principal_amount, 2);
        $processingFee = round($principal * ((float) $product->processing_fee_percentage / 100), 2);
        $insuranceFee = round($principal * ((float) $product->insurance_percentage / 100), 2);
        $vat = round($principal * ((float) $product->vat_percentage / 100), 2);
        $security = round($principal * ((float) $product->security_percentage / 100), 2);
        $charges = round($processingFee + $insuranceFee + $vat, 2);
        $receivable = round($principal - $security - $charges, 2);
        $count = max(1, (int) $loan->number_of_installments);
        $totalRepayment = round((float) $loan->total_repayment, 2);
        $application = $loan->application;
        $duration = (int) ($application?->recommended_duration_months ?: $application?->duration_months ?: 0);
        $installmentAmount = $duration > 0
            ? $this->calculator->weeklyFactorAmount($principal, $duration, $count)
            : round(((float) $loan->interest_amount) / $count, 2);
        $weeklyInstallment = $installmentAmount;

        return [
            'processing_fee' => $processingFee,
            'insurance_fee' => $insuranceFee,
            'vat' => $vat,
            'security_amount' => $security,
            'charges' => $charges,
            'amount_receivable' => $receivable,
            'installment_amount' => $installmentAmount,
            'weekly_installment' => $weeklyInstallment,
            'installment_count' => $count,
            'total_repayment' => $totalRepayment,
        ];
    }

    private function applicationFigures(LoanApplication $application): array
    {
        $amount = (float) ($application->recommended_amount ?: $application->requested_amount);
        $duration = (int) ($application->recommended_duration_months ?: $application->duration_months);

        return $this->calculator->calculate($application->product, $amount, $duration);
    }

    /**
     * Expected total_due per installment, mirroring RepaymentScheduleService::generate().
     */
    private function expectedSplit(Loan $loan): array
    {
        $count = max(1, (int) $loan->number_of_installments);

        if ((float) $loan->interest_amount <= 0.009) {
            $remaining = round((float) $loan->total_repayment, 2);
            $weekly = round($remaining / $count, 2);
            $split = [];
            for ($i = 1; $i <= $count; $i++) {
                $total = $i === $count ? $remaining : min($weekly, $remaining);
                $total = round($total, 2);
                $split[] = $total;
                $remaining = round($remaining - $total, 2);
            }

            return $split;
        }

        return collect($this->schedule->rows($loan))->pluck('total_due')->all();
    }

    private function hasPaymentHistory(Loan $loan): bool
    {
        return $loan->installmentRecords()->exists()
            || $loan->payments()->where('status', 'posted')->exists()
            || DB::table('payment_allocations')
                ->whereIn('loan_installment_id', $loan->installments()->pluck('id'))
                ->exists();
    }

    private function scheduleEligible(Loan $loan): bool
    {
        return in_array($loan->status?->value, ['active', 'overdue'], true)
            && $loan->first_payment_date !== null
            && ! $this->hasPaymentHistory($loan);
    }

    private function loanNeedsScheduleCorrection(Loan $loan): bool
    {
        $count = max(1, (int) $loan->number_of_installments);
        $installments = $loan->installments()->orderBy('installment_number')->get(['total_due']);

        if ($installments->count() !== $count) {
            return true;
        }

        $expected = $this->expectedSplit($loan);
        foreach ($installments->values() as $index => $row) {
            if (round((float) $row->total_due, 2) !== $expected[$index]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count how many existing applications and loans hold outdated figures.
     */
    public function estimate(?int $branchId = null): array
    {
        $applications = LoanApplication::with('product')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();
        $loans = Loan::with('product')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $applicationChanges = 0;
        $applicationSkipped = 0;
        $applicationSamples = [];
        foreach ($applications as $application) {
            if (! $application->product) {
                $applicationSkipped++;
                continue;
            }
            try {
                $needs = $this->applicationNeedsCorrection($application);
            } catch (DomainException $e) {
                $applicationSkipped++;
                continue;
            }
            if ($needs) {
                $applicationChanges++;
                if (count($applicationSamples) < 5) {
                    $applicationSamples[] = [
                        'id' => $application->id,
                        'application_number' => $application->application_number,
                        'old_vat' => $application->calc_vat,
                        'new_vat' => $this->applicationFigures($application)['vat'],
                    ];
                }
            }
        }

        $loanChanges = 0;
        $repaymentChanges = 0;
        $scheduleEligible = 0;
        $scheduleChanges = 0;
        $loanSamples = [];
        $loanSkipped = 0;
        foreach ($loans as $loan) {
            if (! $loan->product) {
                continue;
            }
            if ($this->loanNeedsCorrection($loan)) {
                $loanChanges++;
                if (count($loanSamples) < 5) {
                    $loanSamples[] = [
                        'id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'old_vat' => $loan->calc_vat,
                        'new_vat' => $this->loanFigures($loan)['vat'],
                    ];
                }
            }
            if ($this->loanNeedsRepaymentCorrection($loan)) {
                $repaymentChanges++;
            }
            if ($this->scheduleEligible($loan)) {
                $scheduleEligible++;
                if ($this->loanNeedsScheduleCorrection($loan)) {
                    $scheduleChanges++;
                }
            } elseif ($this->hasPaymentHistory($loan)) {
                $loanSkipped++;
            }
        }

        return [
            'applications' => $applicationChanges,
            'applications_skipped' => $applicationSkipped,
            'loans' => $loanChanges,
            'repayment' => $repaymentChanges,
            'schedule_eligible' => $scheduleEligible,
            'schedule' => $scheduleChanges,
            'loans_with_payments_skipped_for_schedule' => $loanSkipped,
            'application_samples' => $applicationSamples,
            'loan_samples' => $loanSamples,
        ];
    }

    private function applicationNeedsCorrection(LoanApplication $application): bool
    {
        $f = $this->applicationFigures($application);

        return round((float) ($application->calc_vat ?? 0), 2) !== round((float) $f['vat'], 2)
            || round((float) ($application->calc_charges ?? 0), 2) !== round((float) $f['charges'], 2)
            || round((float) ($application->calc_security_amount ?? 0), 2) !== round((float) $f['security_amount'], 2)
            || round((float) ($application->calc_amount_receivable ?? 0), 2) !== round((float) $f['amount_receivable'], 2)
            || round((float) ($application->calc_interest ?? 0), 2) !== round((float) $f['interest'], 2)
            || round((float) ($application->calc_total_repayment ?? 0), 2) !== round((float) $f['total_repayment'], 2);
    }

    private function loanNeedsCorrection(Loan $loan): bool
    {
        $f = $this->loanFigures($loan);

        return round((float) ($loan->calc_vat ?? 0), 2) !== $f['vat']
            || round((float) ($loan->calc_charges ?? $loan->total_fees_and_vat ?? 0), 2) !== $f['charges']
            || round((float) ($loan->total_fees_and_vat ?? 0), 2) !== $f['charges']
            || round((float) ($loan->calc_security_amount ?? 0), 2) !== $f['security_amount']
            || round((float) ($loan->calc_amount_receivable ?? 0), 2) !== $f['amount_receivable']
            || round((float) ($loan->processing_fee ?? 0), 2) !== $f['processing_fee'];
    }

    private function loanNeedsRepaymentCorrection(Loan $loan): bool
    {
        $f = $this->loanFigures($loan);

        return round((float) ($loan->installment_amount ?? 0), 2) !== $f['installment_amount']
            || round((float) ($loan->weekly_installment ?? 0), 2) !== $f['weekly_installment'];
    }

    /**
     * Update VAT/fee/security figures on saved applications and loans, and correct
     * repayment amounts (including regenerating schedules where safe).
     */
    public function correct(?int $branchId = null, bool $includeSchedule = true): array
    {
        return DB::transaction(function () use ($branchId, $includeSchedule) {
            $applicationChanges = 0;
            $applicationSkipped = 0;
            $applications = LoanApplication::with('product')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->get();
            foreach ($applications as $application) {
                try {
                    $f = $this->applicationFigures($application);
                } catch (DomainException $e) {
                    $applicationSkipped++;
                    continue;
                }
                if ($this->applicationNeedsCorrection($application)) {
                    $application->update([
                        'calc_vat' => $f['vat'],
                        'calc_insurance_fee' => $f['insurance_fee'],
                        'calc_processing_fee' => $f['processing_fee'],
                        'calc_security_amount' => $f['security_amount'],
                        'calc_charges' => $f['charges'],
                        'calc_amount_receivable' => $f['amount_receivable'],
                        'calc_total_repayment' => $f['total_repayment'],
                        'calc_interest' => $f['interest'],
                    ]);
                    $applicationChanges++;
                }
            }

            $loanChanges = 0;
            $repaymentChanges = 0;
            $scheduleChanges = 0;
            $loans = Loan::with('product')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->get();
            foreach ($loans as $loan) {
                if (! $loan->product) {
                    continue;
                }
                $f = $this->loanFigures($loan);
                $needsCorrection = $this->loanNeedsCorrection($loan);
                $needsRepayment = $this->loanNeedsRepaymentCorrection($loan);
                if ($needsCorrection || $needsRepayment) {
                    $loan->update([
                        'processing_fee' => $f['processing_fee'],
                        'calc_insurance_fee' => $f['insurance_fee'],
                        'calc_vat' => $f['vat'],
                        'calc_security_amount' => $f['security_amount'],
                        'calc_charges' => $f['charges'],
                        'calc_amount_receivable' => $f['amount_receivable'],
                        'total_fees_and_vat' => $f['charges'],
                        'installment_amount' => $f['installment_amount'],
                        'weekly_installment' => $f['weekly_installment'],
                    ]);
                    if ($needsCorrection) {
                        $loanChanges++;
                    }
                    if ($needsRepayment) {
                        $repaymentChanges++;
                    }
                }

                if ($includeSchedule && $this->scheduleEligible($loan) && $this->loanNeedsScheduleCorrection($loan)) {
                    $loan->installments()->delete();
                    $this->schedule->generate($loan, $loan->first_payment_date);
                    $scheduleChanges++;
                }
            }

            $message = sprintf(
                'VAT computation corrected for %d loan application(s) and %d loan(s). Repayment amounts corrected on %d loan(s); %d repayment schedule(s) regenerated.',
                $applicationChanges,
                $loanChanges,
                $repaymentChanges,
                $scheduleChanges
            );

            if ($applicationChanges + $loanChanges + $repaymentChanges + $scheduleChanges === 0) {
                $message = 'No outdated VAT or repayment figures were found — nothing to correct.';
            }

            return [
                'applications' => $applicationChanges,
                'applications_skipped' => $applicationSkipped,
                'loans' => $loanChanges,
                'repayment' => $repaymentChanges,
                'schedule' => $scheduleChanges,
                'message' => $message,
            ];
        });
    }

    /**
     * Recompute and persist the figures for a single loan using the product's
     * current rates, optionally regenerating its repayment schedule where safe.
     *
     * @return array{loan: int, corrected: bool, repayment: bool, schedule: bool, message: string}
     */
    public function correctLoan(Loan $loan, bool $includeSchedule = true): array
    {
        if (! $loan->product) {
            throw new DomainException('This loan has no linked product; correction cannot be applied.');
        }

        return DB::transaction(function () use ($loan, $includeSchedule) {
            $f = $this->loanFigures($loan);
            $needsCorrection = $this->loanNeedsCorrection($loan);
            $needsRepayment = $this->loanNeedsRepaymentCorrection($loan);

            if ($needsCorrection || $needsRepayment) {
                $loan->update([
                    'processing_fee' => $f['processing_fee'],
                    'calc_insurance_fee' => $f['insurance_fee'],
                    'calc_vat' => $f['vat'],
                    'calc_security_amount' => $f['security_amount'],
                    'calc_charges' => $f['charges'],
                    'calc_amount_receivable' => $f['amount_receivable'],
                    'total_fees_and_vat' => $f['charges'],
                    'installment_amount' => $f['installment_amount'],
                    'weekly_installment' => $f['weekly_installment'],
                ]);
            }

            $scheduleChanged = false;
            if ($includeSchedule && $this->scheduleEligible($loan) && $this->loanNeedsScheduleCorrection($loan)) {
                $loan->installments()->delete();
                $this->schedule->generate($loan, $loan->first_payment_date);
                $scheduleChanged = true;
            }

            if (! $needsCorrection && ! $needsRepayment && ! $scheduleChanged) {
                $message = 'No outdated VAT or repayment figures were found — nothing to correct.';
            } else {
                $changes = [];
                if ($needsCorrection) {
                    $changes[] = 'figures corrected';
                }
                if ($needsRepayment) {
                    $changes[] = 'repayment amounts corrected';
                }
                if ($scheduleChanged) {
                    $changes[] = 'repayment schedule regenerated';
                }
                $message = 'Loan corrected ('.implode('; ', $changes).').';
            }

            return [
                'loan' => $loan->id,
                'corrected' => $needsCorrection || $needsRepayment,
                'repayment' => $needsRepayment,
                'schedule' => $scheduleChanged,
                'message' => $message,
            ];
        });
    }
}
