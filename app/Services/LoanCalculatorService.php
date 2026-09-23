<?php

namespace App\Services;

use App\Models\LoanProduct;
use DomainException;

class LoanCalculatorService
{
    public function installmentCount(LoanProduct $product, int $durationMonths): int
    {
        if ($product->repayment_frequency === 'weekly') {
            $customWeeks = config('vati.installment_weeks', []);
            if (isset($customWeeks[$durationMonths])) {
                return max(1, $customWeeks[$durationMonths]);
            }
            return max(1, $durationMonths * 4);
        }
        return max(1, $durationMonths);
    }

/**
     * Flat weekly interest factor (e.g. 0.0445) charged on the full principal
     * per installment. Returns null when no tier applies.
     */
    public function interestTier(int $durationMonths): ?float
    {
        $tiers = config('vati.interest_tiers', []);

        return isset($tiers[$durationMonths]) ? (float) $tiers[$durationMonths] : null;
    }

    /**
     * Per-installment flat factor applied to the full principal. The configured
     * tier is a weekly factor; monthly installments scale it up (× 4, one month
     * equals four weeks) so the same duration repays the same total regardless
     * of frequency.
     */
    public function periodRate(LoanProduct $product, int $durationMonths): float
    {
        $tier = $this->interestTier($durationMonths);
        if ($tier === null || $durationMonths < 1) {
            return 0.0;
        }

        return $product->repayment_frequency === 'monthly' ? $tier * 4 : $tier;
    }

    /**
     * Display installment amount for a flat loan: floor(total / count). A
     * rate of zero returns an even share of the principal.
     */
    public function perPeriodAmount(float $principal, float $periodRate, int $count): float
    {
        $count = max(1, $count);
        $principal = round($principal, 2);

        if ($periodRate <= 0) {
            return round($principal / $count, 2);
        }

        $totalRepayment = round($principal * $periodRate * $count, 2);

        return intdiv((int) round($totalRepayment * 100), $count) / 100;
    }

    /**
     * Build a flat amortization across $count equal installments. Each
     * installment totals principal × factor (the per-period rate applied to the
     * full principal); the principal is repaid in even shares with the balance
     * as interest. The last installment absorbs the rounding remainder.
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

        $totalRepayment = round($principal * $periodRate * $count, 2);
        $totalInterest = round($totalRepayment - $principal, 2);

        $perPeriod = round($totalRepayment / $count, 2);
        $even = round($principal / $count, 2);

        for ($i = 1; $i <= $count; $i++) {
            $principalPart = $i === $count ? $remaining : min($even, $remaining);
            $principalPart = round($principalPart, 2);
            $total = $i === $count ? round($totalRepayment - $perPeriod * ($count - 1), 2) : $perPeriod;
            $interest = round($total - $principalPart, 2);

            $rows[] = [
                'installment_number' => $i,
                'principal_due' => $principalPart,
                'interest_due' => $interest,
                'total_due' => $total,
                'outstanding_balance' => round($remaining - $principalPart, 2),
            ];
            $remaining = round($remaining - $principalPart, 2);
        }

        return [
            'installments' => $rows,
            'principal' => $principal,
            'interest' => $totalInterest,
            'total_repayment' => $totalRepayment,
            'installment_amount' => $perPeriod,
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
        $totalInterest = round($figures['interest'], 2);

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
            'interest' => $totalInterest,
            'total_interest' => $totalInterest,
            'interest_rate' => $tier ?? 0.0,
            'processing_fee' => round($processingFee, 2),
            'insurance_fee' => round($insuranceFee, 2),
            'vat' => round($vat, 2),
            'security_amount' => round($securityAmount, 2),
            'charges' => round($totalCharges, 2),
            'amount_receivable' => $receivable,
            'total_repayment' => $totalRepayment,
            'installment_count' => $installmentCount,
            'installment_amount' => $tier === null
                ? 0.0
                : $this->perPeriodAmount($principal, $periodRate, $installmentCount),
        ];
    }
}
