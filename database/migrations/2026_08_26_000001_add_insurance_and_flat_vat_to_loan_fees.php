<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_products', 'insurance_percentage')) {
                $table->decimal('insurance_percentage', 8, 4)->default(0)->after('transaction_fee_percentage');
            }
        });

        foreach (['loan_applications', 'loans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'calc_insurance_fee')) {
                    $table->decimal('calc_insurance_fee', 18, 2)->nullable()->after('calc_membership_fee');
                }
                if (! Schema::hasColumn($tableName, 'calc_vat')) {
                    $table->decimal('calc_vat', 18, 2)->nullable()->after('calc_insurance_fee');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            if (Schema::hasColumn('loan_products', 'insurance_percentage')) {
                $table->dropColumn('insurance_percentage');
            }
        });

        foreach (['loan_applications', 'loans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $drop = array_filter(['calc_insurance_fee', 'calc_vat'], fn ($column) => Schema::hasColumn($tableName, $column));
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};
