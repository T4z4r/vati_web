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

    public function calculate(LoanProduct $product, float $principal, int $durationMonths): array
    {
        if ($principal < (float) $product->minimum_amount || $principal > (float) $product->maximum_amount) {
            throw new DomainException('Loan amount is outside the product limits.');
        }

        if ($durationMonths < $product->minimum_duration_months || $durationMonths > $product->maximum_duration_months) {
            throw new DomainException('Loan duration is outside the product limits.');
        }

        $installmentCount = $this->installmentCount($product, $durationMonths);
        // Fees and security are withheld at issuance, never added to debt.
        $totalRepayment = round($principal, 2);
        $interest = 0.0;

        $principal = round($principal, 2);
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
            'principal' => round($principal, 2),
            'interest' => round($interest, 2),
            'processing_fee' => round($processingFee, 2),
            'insurance_fee' => round($insuranceFee, 2),
            'vat' => round($vat, 2),
            'security_amount' => round($securityAmount, 2),
            'charges' => round($totalCharges, 2),
            'amount_receivable' => $receivable,
            'total_repayment' => round($totalRepayment, 2),
            'installment_count' => $installmentCount,
            'installment_amount' => intdiv((int) round($totalRepayment * 100), $installmentCount) / 100,
        ];
    }
}
