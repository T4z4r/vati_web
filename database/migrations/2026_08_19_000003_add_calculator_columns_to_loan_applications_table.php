<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->decimal('calc_interest', 18, 2)->nullable()->after('increment_amount');
            $table->decimal('calc_processing_fee', 18, 2)->nullable()->after('calc_interest');
            $table->decimal('calc_processing_fee_vat', 18, 2)->nullable()->after('calc_processing_fee');
            $table->decimal('calc_transaction_fee', 18, 2)->nullable()->after('calc_processing_fee_vat');
            $table->decimal('calc_transaction_fee_vat', 18, 2)->nullable()->after('calc_transaction_fee');
            $table->decimal('calc_membership_fee', 18, 2)->nullable()->after('calc_transaction_fee_vat');
            $table->decimal('calc_security_amount', 18, 2)->nullable()->after('calc_membership_fee');
            $table->decimal('calc_charges', 18, 2)->nullable()->after('calc_security_amount');
            $table->decimal('calc_amount_receivable', 18, 2)->nullable()->after('calc_charges');
            $table->decimal('calc_total_repayment', 18, 2)->nullable()->after('calc_amount_receivable');
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'calc_interest', 'calc_processing_fee', 'calc_processing_fee_vat',
                'calc_transaction_fee', 'calc_transaction_fee_vat', 'calc_membership_fee',
                'calc_security_amount', 'calc_charges', 'calc_amount_receivable', 'calc_total_repayment',
            ]);
        });
    }
};
