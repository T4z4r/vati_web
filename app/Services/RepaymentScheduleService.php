<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\CarbonInterface;

class RepaymentScheduleService
{
    public function generate(Loan $loan, CarbonInterface $firstPaymentDate): void
    {
        $count = max(1, (int) $loan->number_of_installments);

        if ((float) $loan->interest_amount <= 0.009) {
            // Interest-free schedule: every instalment counts fully toward the balance.
            $remainingTotal = round((float) $loan->total_repayment, 2);
            $perInstallment = floor(($remainingTotal / $count) * 100) / 100;
            $weekly = $loan->product->repayment_frequency === 'weekly';

            for ($i = 1; $i <= $count; $i++) {
                $total = $i === $count ? $remainingTotal : min($perInstallment, $remainingTotal);
                $total = round($total, 2);
                $loan->installments()->create([
                    'installment_number' => $i,
                    'due_date' => $weekly ? $firstPaymentDate->copy()->addWeeks($i - 1) : $firstPaymentDate->copy()->addMonths($i - 1),
                    'principal_due' => $total,
                    'interest_due' => 0,
                    'total_due' => $total,
                    'outstanding_balance' => max(0, round((float) $loan->total_repayment - (($i - 1) * $perInstallment), 2)),
                ]);
                $remainingTotal = round($remainingTotal - $total, 2);
            }

            return;
        }

        // Legacy interest-bearing loans keep the principal/interest split.
        $remainingPrincipal = (float) $loan->principal_amount;
        $remainingInterest = (float) $loan->interest_amount;
        $cumulativeBalance = (float) $loan->total_repayment;
        $weekly = $loan->product->repayment_frequency === 'weekly';

        for ($i = 1; $i <= $count; $i++) {
            $principal = $i === $count ? $remainingPrincipal : round((float) $loan->principal_amount / $count, 2);
            $interest = $i === $count ? $remainingInterest : round((float) $loan->interest_amount / $count, 2);
            $total = round($principal + $interest, 2);
            $loan->installments()->create([
                'installment_number' => $i,
                'due_date' => $weekly ? $firstPaymentDate->copy()->addWeeks($i - 1) : $firstPaymentDate->copy()->addMonths($i - 1),
                'principal_due' => $principal,
                'interest_due' => $interest,
                'total_due' => $total,
                'outstanding_balance' => max(0, round($cumulativeBalance, 2)),
            ]);
            $remainingPrincipal = round($remainingPrincipal - $principal, 2);
            $remainingInterest = round($remainingInterest - $interest, 2);
            $cumulativeBalance = round($cumulativeBalance - $total, 2);
        }
    }
}
