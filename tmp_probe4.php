<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LoanProduct;
use App\Models\Loan;
use Illuminate\Support\Facades\DB;

echo "=== loan_products: what the DB actually holds ===\n";
foreach (LoanProduct::orderBy('code')->get() as $p) {
    printf("id=%-3d code=%-16s vat=%-8s proc=%-8s ins=%-8s sec=%-8s\n",
        $p->id, $p->code, $p->vat_percentage, $p->processing_fee_percentage, $p->insurance_percentage, $p->security_percentage);
}

echo "\n=== loans: all rows, all columns that relate to fee/vat/charges ===\n";
$cols = DB::select('SHOW COLUMNS FROM loans');
$names = array_map(fn ($c) => $c->Field, $cols);
$interesting = array_values(array_filter($names, fn ($n) => preg_match('/vat|fee|charg|secur|receiv|fees/', $n)));
echo 'columns: '.implode(', ', $interesting)."\n";

$rows = DB::table('loans')->get();
printf("loans in DB: %d\n\n", $rows->count());
