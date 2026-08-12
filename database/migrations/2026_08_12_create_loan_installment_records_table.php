<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('loan_cycle_id')->constrained('loan_cycles')->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('payment_date');

            // Payment Details
            $table->decimal('principal_amount', 15, 2)->default(0);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('interest_exemption', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);

            // Payment Status
            $table->boolean('is_paid')->default(false);
            $table->date('actual_payment_date')->nullable();

            // Collector & Verification
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->text('collector_notes')->nullable();

            // Signatures
            $table->text('collector_signature')->nullable();
            $table->text('branch_manager_signature')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['loan_id', 'installment_number']);
            $table->index('loan_cycle_id');
            $table->index('payment_date');
            $table->index('is_paid');
            $table->index(['loan_id', 'is_paid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installment_records');
    }
};
