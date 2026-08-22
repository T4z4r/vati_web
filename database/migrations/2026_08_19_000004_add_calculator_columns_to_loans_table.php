<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'calc_security_amount')) {
                $table->decimal('calc_security_amount', 18, 2)->nullable()->after('total_fees_and_vat');
            }
            if (! Schema::hasColumn('loans', 'calc_amount_receivable')) {
                $table->decimal('calc_amount_receivable', 18, 2)->nullable()->after('calc_security_amount');
            }
            if (! Schema::hasColumn('loans', 'calc_processing_fee_vat')) {
                $table->decimal('calc_processing_fee_vat', 18, 2)->nullable()->after('calc_amount_receivable');
            }
            if (! Schema::hasColumn('loans', 'calc_transaction_fee_vat')) {
                $table->decimal('calc_transaction_fee_vat', 18, 2)->nullable()->after('calc_processing_fee_vat');
            }
            if (! Schema::hasColumn('loans', 'calc_membership_fee')) {
                $table->decimal('calc_membership_fee', 18, 2)->nullable()->after('calc_transaction_fee_vat');
            }
            if (! Schema::hasColumn('loans', 'calc_charges')) {
                $table->decimal('calc_charges', 18, 2)->nullable()->after('calc_membership_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $columns = ['calc_security_amount', 'calc_amount_receivable', 'calc_processing_fee_vat', 'calc_transaction_fee_vat', 'calc_membership_fee', 'calc_charges'];
            $table->dropColumn(array_filter($columns, fn ($col) => Schema::hasColumn('loans', $col)));
        });
    }
};
