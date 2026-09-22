<?php

namespace App\Services;

use App\Models\LoanProduct;
use DomainException;

class LoanCalculatorService
{
    public function installmentCount(LoanProduct $product, int $durationMonths): int
    {
        return $product->repayment_frequency === 'weekly'
            ? max(1, (int) round($durationMonths * 52 / 12))
            : max(1, $durationMonths);
    }

    /**
     * Monthly reducing-balance interest rate (as a factor, e.g. 0.036) for a
     * given duration. Returns null when no tier applies.
     */
    public function interestTier(int $durationMonths): ?float
    {
        $tiers = config('vati.interest_tiers', []);

        return isset($tiers[$durationMonths]) ? (float) $tiers[$durationMonths] : null;
    }

    /**
     * Reducing-balance interest rate charged per repayment period. The
     * configured tier is a monthly rate used directly; weekly periods receive
     * a proportional period rate.
     */
    public function periodRate(LoanProduct $product, int $durationMonths): float
    {
        $tier = $this->interestTier($durationMonths);
        if ($tier === null || $durationMonths < 1) {
            return 0.0;
        }
        $monthly = $tier;

        return $product->repayment_frequency === 'weekly' ? $monthly * 12 / 52 : $monthly;
    }

    /**
     * Build a reducing-balance amortization across $count equal installments.
     * Interest accrues on the outstanding principal each period and the last
     * installment absorbs the rounding remainder.
     */
    public function amortize(float $principal, float $periodRate, int $count): array
    {
        $count = max(1, $count);
        $principal = round($principal, 2);

        $rows = [];
        $remaining = $principal;

        if ($periodRate <= 0) {
            $even = round($principal / $count, 2);
            for ($i = 1; $i <= $count; $i++) {
                $total = $i === $count ? $remaining : min($even, $remaining);
                $total = round($total, 2);
                $rows[] = [
                    'installment_number' => $i,
                    'principal_due' => $total,
                    'interest_due' => 0.0,
                    'total_due' => $total,
                    'outstanding_balance' => round($remaining - $total, 2),
                ];
                $remaining = round($remaining - $total, 2);
            }

            return [
                'installments' => $rows,
                'principal' => $principal,
                'interest' => 0.0,
                'total_repayment' => $principal,
                'installment_amount' => round($principal / $count, 2),
            ];
        }

        $growth = pow(1 + $periodRate, $count);
        $payment = round(($principal * $periodRate * $growth) / ($growth - 1), 2);

        for ($i = 1; $i <= $count; $i++) {
            $interest = round($remaining * $periodRate, 2);
            $principalPart = $i === $count
                ? $remaining
                : min(max(0, $payment - $interest), $remaining);
            $principalPart = round($principalPart, 2);
            $total = round($principalPart + $interest, 2);

            $rows[] = [
                'installment_number' => $i,
                'principal_due' => $principalPart,
                'interest_due' => $interest,
                'total_due' => $total,
                'outstanding_balance' => round($remaining - $principalPart, 2),
            ];
            $remaining = round($remaining - $principalPart, 2);
        }

        $totalRepayment = round(array_sum(array_column($rows, 'total_due')), 2);

        return [
            'installments' => $rows,
            'principal' => $principal,
            'interest' => round($totalRepayment - $principal, 2),
            'total_repayment' => $totalRepayment,
            'installment_amount' => $payment,
        ];
    }

    public function calculate(LoanProduct $product, float $principal, int $durationMonths): array
    {
        if ($principal < (float) $product->minimum_amount || $principal > (float) $product->maximum_amount) {
            throw new DomainException('Loan amount is outside the product limits.');
        }

        if ($durationMonths < $product->minimum_duration_months || $durationMonths > $product->maximum_duration_months) {
            throw new DomainException('Loan duration is outside the product limits.');
        }

        $principal = round($principal, 2);
        $installmentCount = $this->installmentCount($product, $durationMonths);
        $tier = $this->interestTier($durationMonths);
        $periodRate = $this->periodRate($product, $durationMonths);
        $figures = $this->amortize($principal, $periodRate, $installmentCount);

        // Fees and security are withheld at issuance, never added to debt.
        $totalRepayment = round($figures['total_repayment'], 2);
        $interest = round($figures['interest'], 2);

        $processingFee = round($principal * ((float) $product->processing_fee_percentage / 100), 2);
        $insuranceFee = round($principal * ((float) $product->insurance_percentage / 100), 2);
        $vat = round($principal * ((float) $product->vat_percentage / 100), 2);
        $securityAmount = round($principal * ((float) $product->security_percentage / 100), 2);
        $totalCharges = $processingFee + $insuranceFee + $vat;
        $receivable = round($principal - $securityAmount - $totalCharges, 2);
        if ($receivable < 0) {
            throw new DomainException('Loan fees and security cannot exceed the principal amount.');
        }

        return [
            'principal' => $principal,
            'interest' => $interest,
            'interest_rate' => $tier ?? 0.0,
            'processing_fee' => round($processingFee, 2),
            'insurance_fee' => round($insuranceFee, 2),
            'vat' => round($vat, 2),
            'security_amount' => round($securityAmount, 2),
            'charges' => round($totalCharges, 2),
            'amount_receivable' => $receivable,
            'total_repayment' => $totalRepayment,
            'installment_count' => $installmentCount,
            'installment_amount' => intdiv((int) round($totalRepayment * 100), $installmentCount) / 100,
        ];
    }
}