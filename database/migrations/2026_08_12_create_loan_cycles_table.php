<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->string('business_name')->nullable();
            $table->string('cycle_type')->default('main'); // 'main', 'refinancing'
            $table->boolean('is_main_cycle')->default(true);
            $table->boolean('is_refinancing_cycle')->default(false);

            // Loan Details
            $table->decimal('principal_amount', 15, 2)->default(0);
            $table->decimal('adjusted_principal_amount', 15, 2)->default(0);
            $table->decimal('interest_rate', 8, 4)->default(0); // In percentage
            $table->date('disbursement_date')->nullable();
            $table->date('first_payment_date')->nullable();

            // Fees
            $table->decimal('admission_fee', 15, 2)->default(0);
            $table->decimal('processing_fee', 15, 2)->default(0);
            $table->decimal('transaction_charges', 15, 2)->default(0);
            $table->decimal('increment_amount', 15, 2)->default(0);
            $table->decimal('refinancing_amount', 15, 2)->default(0);
            $table->decimal('other_charges', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_fees_and_vat', 15, 2)->default(0);

            // Repayment Details
            $table->decimal('total_with_interest', 15, 2)->default(0);
            $table->decimal('weekly_installment', 15, 2)->default(0);
            $table->integer('total_installments')->default(0);

            // Status
            $table->string('status')->default('active'); // active, completed, closed
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('loan_id');
            $table->index('disbursement_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_cycles');
    }
};
