<?php

namespace App\Services;

use App\Models\LoanProduct;
use DomainException;

class LoanCalculatorService
{
    /** Fixed weekly payment factors per loan duration in months (weekly payment = principal x factor). */
    public const WEEKLY_PAYMENT_FACTORS = [6 => 0.0445, 8 => 0.0360, 12 => 0.0295];

    public static function weeklyPaymentFactor(int $durationMonths): ?float
    {
        return self::WEEKLY_PAYMENT_FACTORS[$durationMonths] ?? null;
    }

    public function installmentCount(LoanProduct $product, int $durationMonths): int
    {
        return $product->repayment_frequency === 'weekly'
            ? max(1, (int) round($durationMonths * 52 / 12))
            : max(1, $durationMonths);
    }

    public function calculate(LoanProduct $product, float $principal, int $durationMonths): array
    {
        if ($principal < (float) $product->minimum_amount || $principal > (float) $product->maximum_amount) {
            throw new DomainException('Loan amount is outside the product limits.');
        }

        if ($durationMonths < $product->minimum_duration_months || $durationMonths > $product->maximum_duration_months) {
            throw new DomainException('Loan duration is outside the product limits.');
        }

        $installmentCount = $this->installmentCount($product, $durationMonths);
        $factor = $product->repayment_frequency === 'weekly' ? self::weeklyPaymentFactor($durationMonths) : null;

        if ($factor !== null) {
            // Fixed weekly payment schedule: each weekly payment = principal x factor.
            $weeklyInstallment = round($principal * $factor, 2);
            $totalRepayment = round($weeklyInstallment * $installmentCount, 2);
        } else {
            // Interest-free lending: only the principal is repayable.
            $totalRepayment = round($principal, 2);
            $weeklyInstallment = null;
        }
        $interest = 0.0;

        $processingFee = $principal * ((float) $product->processing_fee_percentage / 100);
        $insuranceFee = $principal * ((float) $product->insurance_percentage / 100);
        $vat = $principal * ((float) $product->vat_percentage / 100);
        $securityAmount = $principal * ((float) $product->security_percentage / 100);
        $totalCharges = $processingFee + $insuranceFee + $vat;

        return [
            'principal' => round($principal, 2),
            'interest' => round($interest, 2),
            'processing_fee' => round($processingFee, 2),
            'insurance_fee' => round($insuranceFee, 2),
            'vat' => round($vat, 2),
            'security_amount' => round($securityAmount, 2),
            'charges' => round($totalCharges, 2),
            'amount_receivable' => round($principal - $securityAmount, 2),
            'total_repayment' => round($totalRepayment, 2),
            'installment_count' => $installmentCount,
            'installment_amount' => $factor !== null
                ? $weeklyInstallment
                : round(round($principal, 2) / $installmentCount, 2),
        ];
    }
}