<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanCalculatorService;

echo "=== LoanProduct vat config ===\n";
foreach (LoanProduct::select('code', 'name', 'vat_percentage', 'processing_fee_percentage', 'insurance_percentage', 'security_percentage')->orderBy('code')->get() as $p) {
    printf("%-14s vat=%-6s proc=%-6s ins=%-6s sec=%-6s\n",
        $p->code, $p->vat_percentage, $p->processing_fee_percentage, $p->insurance_percentage, $p->security_percentage);
}

echo "\n=== Loans persisted figures (what the API/web actually show) ===\n";
foreach (Loan::select('loan_number', 'calc_vat', 'calc_charges', 'total_fees_and_vat', 'calc_amount_receivable', 'principal_amount', 'total_repayment')->get() as $l) {
    printf("%-18s principal=%-12s calc_vat=%-8s calc_charges=%-8s fees_and_vat=%-8s receivable=%-8s total_repay=%-8s\n",
        $l->loan_number, $l->principal_amount, $l->calc_vat, $l->calc_charges, $l->total_fees_and_vat, $l->calc_amount_receivable, $l->total_repayment);
}

echo "\n=== Calculator output for the VATI-WEEKLY product at 1,000,000 / 6 months ===\n";
$product = LoanProduct::where('code', 'VATI-WEEKLY')->first();
if ($product) {
    $calc = new LoanCalculatorService();
    try {
        $fig = $calc->calculate($product, 1000000, 6);
        foreach ($fig as $k => $v) {
            printf("%-20s %s\n", $k, is_array($v) ? json_encode($v) : $v);
        }
    } catch (\Throwable $e) {
        echo 'ERROR: '.$e->getMessage()."\n";
    }
}
