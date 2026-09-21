<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanCalculatorService;

echo "=== LoanProduct vat config ===\n";
LoanProduct::select('code', 'name', 'vat_percentage', 'processing_fee_percentage', 'insurance_percentage', 'security_percentage')
    ->orderBy('code')->get()->each(function ($p) {
        printf("%-14s vat=%-8s proc=%-8s ins=%-8s sec=%-8s\n",
            $p->code, $p->vat_percentage, $p->processing_fee_percentage, $p->insurance_percentage, $p->security_percentage);
    });

echo "\n=== Loans: persisted calc columns ===\n";
Loan::select('id', 'loan_number', 'principal_amount', 'calc_vat', 'calc_charges', 'total_fees_and_vat', 'calc_amount_receivable', 'total_repayment')
    ->get()->each(function ($l) {
        printf("L%-4d %-20s principal=%-12s calc_vat=%-8s calc_charges=%-8s fees_and_vat=%-8s receivable=%-8s total_repay=%-8s\n",
            $l->id, $l->loan_number, $l->principal_amount, $l->calc_vat, $l->calc_charges, $l->total_fees_and_vat, $l->calc_amount_receivable, $l->total_repayment);
    });

echo "\n=== Calculator output for VATI-WEEKLY @ 1,000,000 / 6 months ===\n";
$product = LoanProduct::where('code', 'VATI-WEEKLY')->first();
if ($product) {
    try {
        $fig = (new LoanCalculatorService())->calculate($product, 1000000, 6);
        foreach ($fig as $k => $v) {
            printf("%-22s %s\n", $k, $v);
        }
    } catch (Throwable $e) {
        echo 'ERROR: '.$e->getMessage()."\n";
    }
}
