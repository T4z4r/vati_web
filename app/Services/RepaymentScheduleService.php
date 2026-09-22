<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\CarbonInterface;

class RepaymentScheduleService
{
    public function __construct(private LoanCalculatorService $calculator) {}

    /**
     * Resolve the approved tenure of a loan, falling back to a value derived
     * from the installment count when the application is unavailable.
     */
    private function durationMonths(Loan $loan, int $count): int
    {
        $application = $loan->application;
        if ($application && ((int) ($application->recommended_duration_months ?: $application->duration_months)) >= 1) {
            return (int) ($application->recommended_duration_months ?: $application->duration_months);
        }

        return $loan->product->repayment_frequency === 'weekly'
            ? (int) round($count * 12 / 52)
            : $count;
    }

    /**
     * Deterministic schedule rows for a loan, mirroring LoanCalculatorService.
     * Interest-bearing loans use reducing-balance amortization of the principal.
     */
    public function rows(Loan $loan): array
    {
        $count = max(1, (int) $loan->number_of_installments);
        $product = $loan->product;
        $duration = $this->durationMonths($loan, $count);
        $rate = $this->calculator->periodRate($product, $duration);

        return $this->calculator->amortize((float) $loan->principal_amount, $rate, $count)['installments'];
    }

    public function generate(Loan $loan, CarbonInterface $firstPaymentDate): void
    {
        $count = max(1, (int) $loan->number_of_installments);

        if ((float) $loan->interest_amount <= 0.009) {
            // Interest-free schedule: every instalment counts fully toward the balance,
            // split evenly across the installments (amount / N).
            $remainingTotal = round((float) $loan->total_repayment, 2);
            $weeklyInstallment = round($remainingTotal / $count, 2);
            $weekly = $loan->product->repayment_frequency === 'weekly';

            for ($i = 1; $i <= $count; $i++) {
                $total = $i === $count ? $remainingTotal : min($weeklyInstallment, $remainingTotal);
                $total = round($total, 2);
                $loan->installments()->create([
                    'installment_number' => $i,
                    'due_date' => $weekly ? $firstPaymentDate->copy()->addWeeks($i - 1) : $firstPaymentDate->copy()->addMonths($i - 1),
                    'principal_due' => $total,
                    'interest_due' => 0,
                    'total_due' => $total,
                    'outstanding_balance' => max(0, round((float) $loan->total_repayment - (($i - 1) * $weeklyInstallment), 2)),
                ]);
                $remainingTotal = round($remainingTotal - $total, 2);
            }

            return;
        }

        // Reducing-balance schedule: interest accrues on the outstanding principal.
        $weekly = $loan->product->repayment_frequency === 'weekly';
        foreach ($this->rows($loan) as $row) {
            $loan->installments()->create([
                'installment_number' => $row['installment_number'],
                'due_date' => $weekly ? $firstPaymentDate->copy()->addWeeks($row['installment_number'] - 1) : $firstPaymentDate->copy()->addMonths($row['installment_number'] - 1),
                'principal_due' => $row['principal_due'],
                'interest_due' => $row['interest_due'],
                'total_due' => $row['total_due'],
                'outstanding_balance' => max(0, round($row['outstanding_balance'], 2)),
            ]);
        }
    }
}