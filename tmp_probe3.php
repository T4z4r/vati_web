<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== loan count ===\n";
echo Loan::count()." loans\n";

echo "\n=== loans: numeric calc/columns that exist ===\n";
$cols = DB::select('SHOW COLUMNS FROM loans');
$colNames = array_map(fn ($c) => $c->Field, $cols);
$wanted = ['loan_number','principal_amount','calc_vat','vat_percentage','calc_processing_fee','calc_insurance_fee','calc_security_amount','calc_charges','calc_amount_receivable','total_fees_and_vat','total_fees_and_vat','calc_amount_receivable','principal_balance','total_balance'];
foreach ($wanted as $w) { if (in_array($w,$colNames)) { echo "HAS column: $w\n"; } else { echo "MISSING column: $w\n"; } }

echo "\n=== first 15 loans raw columns ===\n";
foreach (Loan::select('id','loan_number','principal_amount','calc_vat','total_fees_and_vat','total_balance')->take(15)->get() as $l) {
    printf("id=%-4s %-18s principal=%-10s calc_vat=%-6s fees_and_vat=%-10s total_balance=%-10s\n", $l->id, $l->loan_number, $l->principal_amount, $l->calc_vat, $l->total_fees_and_vat, $l->total_balance);
}
