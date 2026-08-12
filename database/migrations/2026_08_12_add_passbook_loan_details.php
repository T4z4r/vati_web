<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Passbook loan information fields
            $table->string('business_name')->nullable()->after('loan_product_id');
            $table->string('loan_cycle')->default('main')->after('business_name'); // main or refinancing
            $table->decimal('interest_rate', 8, 4)->nullable()->after('loan_cycle');
            
            // Fee fields
            $table->decimal('admission_fee', 18, 2)->default(0)->after('interest_rate');
            $table->decimal('processing_fee', 18, 2)->default(0)->after('admission_fee');
            $table->decimal('transaction_charges', 18, 2)->default(0)->after('processing_fee');
            $table->decimal('other_charges', 18, 2)->default(0)->after('transaction_charges');
            $table->decimal('total_fees_and_vat', 18, 2)->default(0)->after('other_charges');
            
            // Weekly installment
            $table->decimal('weekly_installment', 18, 2)->default(0)->after('total_fees_and_vat');
            
            // Adjusted principal
            $table->decimal('adjusted_principal_amount', 18, 2)->nullable()->after('principal_amount');
            
            // Refinancing fields
            $table->decimal('refinancing_amount', 18, 2)->default(0)->after('adjusted_principal_amount');
            $table->decimal('increment_amount', 18, 2)->default(0)->after('refinancing_amount');
            
            // Add indexes
            $table->index(['loan_cycle', 'status']);
        });

        // Create loan_security_transactions table for passbook security tracking
        Schema::create('loan_security_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date')->index();
            $table->decimal('security_amount', 18, 2)->default(0);
            $table->decimal('withdrawal_amount', 18, 2)->default(0);
            $table->decimal('balance', 18, 2)->default(0);
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['loan_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_security_transactions');
        
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['loan_cycle', 'status']);
            $table->dropColumn([
                'business_name',
                'loan_cycle',
                'interest_rate',
                'admission_fee',
                'processing_fee',
                'transaction_charges',
                'other_charges',
                'total_fees_and_vat',
                'weekly_installment',
                'adjusted_principal_amount',
                'refinancing_amount',
                'increment_amount',
            ]);
        });
    }
};
