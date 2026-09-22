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
     * Flat interest factor (e.g. 0.036) for the whole tenure of a given
     * duration, expressed as a factor of the principal. Returns null when no
     * tier applies.
     */
    public function interestTier(int $durationMonths): ?float
    {
        $tiers = config('vati.interest_tiers', []);

        return isset($tiers[$durationMonths]) ? (float) $tiers[$durationMonths] : null;
    }

    /**
     * Flat interest factor charged over the whole tenure. The configured tier
     * applies regardless of repayment frequency; weekly periods spread the same
     * factor across the installment count.
     */
    public function periodRate(LoanProduct $product, int $durationMonths): float
    {
        $tier = $this->interestTier($durationMonths);
        if ($tier === null || $durationMonths < 1) {
            return 0.0;
        }

        return $tier;
    }

    public function weeklyFactorAmount(float $principal, int $durationMonths, int $weeks): float
    {
        $factor = $this->interestTier($durationMonths) ?? 0.0;

        return round($weeks > 0 ? ($principal * $factor) / $weeks : 0.0, 2);
    }

    /**
     * Build a flat amortization across $count equal installments. The rate is
     * the total interest factor for the whole tenure: total interest is
     * principal × factor, spread evenly across the installments and added to
     * an equal share of principal. The last installment absorbs the rounding
     * remainder.
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

        $totalInterest = round($principal * $periodRate, 2);
        $totalRepayment = round($principal + $totalInterest, 2);

        $even = round($principal / $count, 2);
        $baseInterest = round($totalInterest / $count, 2);
        $allocatedInterest = 0.0;

        for ($i = 1; $i <= $count; $i++) {
            $principalPart = $i === $count ? $remaining : min($even, $remaining);
            $principalPart = round($principalPart, 2);
            $interest = $i === $count ? round($totalInterest - $allocatedInterest, 2) : $baseInterest;
            $total = round($principalPart + $interest, 2);

            $rows[] = [
                'installment_number' => $i,
                'principal_due' => $principalPart,
                'interest_due' => $interest,
                'total_due' => $total,
                'outstanding_balance' => round($remaining - $principalPart, 2),
            ];
            $remaining = round($remaining - $principalPart, 2);
            $allocatedInterest = round($allocatedInterest + $interest, 2);
        }

        return [
            'installments' => $rows,
            'principal' => $principal,
            'interest' => $totalInterest,
            'total_repayment' => $totalRepayment,
            'installment_amount' => round($principal > 0 ? $totalRepayment / $count : 0.0, 2),
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
            'installment_amount' => $this->weeklyFactorAmount($principal, $durationMonths, $installmentCount),
        ];
    }
}
