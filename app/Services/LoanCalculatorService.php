<?php

namespace App\Services;

use App\Models\LoanProduct;
use DomainException;

class LoanCalculatorService
{
    public function calculate(LoanProduct $product, float $principal, int $durationMonths): array
    {
        if ($principal < (float) $product->minimum_amount || $principal > (float) $product->maximum_amount) {
            throw new DomainException('Loan amount is outside the product limits.');
        }

        if ($durationMonths < $product->minimum_duration_months || $durationMonths > $product->maximum_duration_months) {
            throw new DomainException('Loan duration is outside the product limits.');
        }

        $interest = $principal * ((float) $product->annual_interest_rate / 100) * ($durationMonths / 12);
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
            'total_repayment' => round($principal + $interest, 2),
        ];
    }
}