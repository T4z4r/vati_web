<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class LoanProductSeeder extends Seeder
{
    public function run(): void
    {
        LoanProduct::updateOrCreate(['code' => 'VATI-WEEKLY'], ['name' => 'VATI Weekly Business Loan', 'minimum_amount' => 100000, 'maximum_amount' => 10000000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12, 'annual_interest_rate' => 24, 'interest_method' => 'flat', 'repayment_frequency' => 'weekly', 'security_percentage' => 10, 'processing_fee_percentage' => 1, 'insurance_percentage' => 1.5, 'transaction_fee_percentage' => 0, 'membership_fee' => 0, 'vat_percentage' => 0.18, 'required_group_witnesses' => 2, 'status' => true]);
    }
}
