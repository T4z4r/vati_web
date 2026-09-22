<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rates = [
            'processing_fee_percentage' => 1,
            'insurance_percentage' => 1.5,
            'vat_percentage' => 0.18,
            'security_percentage' => 10,
            'transaction_fee_percentage' => 0,
        ];

        Schema::table('loan_products', function (Blueprint $table) use ($rates) {
            foreach ($rates as $column => $rate) {
                $table->decimal($column, 8, 4)->default($rate)->change();
            }
        });

        // Update product configuration, preserving saved loan and payment history.
        DB::table('loan_products')->update($rates + ['membership_fee' => 0, 'annual_interest_rate' => 0]);
        foreach (['default_processing_fee' => '1.00', 'default_transaction_fee' => '0.00', 'default_vat_rate' => '0.18'] as $key => $value) {
            DB::table('system_settings')->where('key', $key)->update(['value' => $value]);
        }
    }

    public function down(): void
    {
        // Previous product-specific rates cannot be reconstructed safely.
    }
};
