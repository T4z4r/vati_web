<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanCalculatorService;

echo "=== LoanProduct vat config ===\n";
foreach (LoanProduct::select('id','code','name','vat_percentage','processing_fee_percentage','insurance_percentage','security_percentage')->orderBy('id')->get() as $p) {
    printf("id=%-4d %-14s vat=%-7s proc=%-7s ins=%-7s sec=%-7s\n",
        $p->id, $p->code, $p->vat_percentage, $p->processing_fee_percentage, $p->insurance_percentage, $p->security_percentage);
}

echo "\n=== Persisted loans: the calc_* columns actually stored ===\n";
foreach (Loan::select('loan_number','principal_amount','calc_vat','calc_charges','total_fees_and_vat','calc_security_amount','calc_amount_receivable','total_repayment')->orderBy('id')->get() as $l) {
    printf("%-18s principal=%-12s calc_vat=%-8s calc_charges=%-8s fees_and_vat=%-8s security=%-8s receivable=%-8s total_repay=%-8s\n",
        $l->loan_number, $l->principal_amount, $l->calc_vat, $l->calc_charges, $l->total_fees_and_vat, $l->calc_security_amount, $l->calc_amount_receivable, $l->total_repayment);
}

echo "\n=== Calculator for VATI-WEEKLY @ 1,000,000 / 6 mo ===\n";
$product = LoanProduct::where('code','VATI-WEEKLY')->first();
if ($product) {
    $calc = new LoanCalculatorService();
    $fig = $calc->calculate($product, 1000000.0, 6);
    foreach (['principal','interest','processing_fee','insurance_fee','vat','security_amount','charges','amount_receivable','total_repayment','installment_count','installment_amount'] as $k) {
        printf("%-18s %s\n", $k, $fig[$k] ?? '(missing)');
    }
}
