<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code')->unique();
            $table->string('policy_title');
            $table->string('category'); // e.g., 'passbook', 'loan', 'membership', 'payment'
            $table->text('description');
            $table->text('detailed_content')->nullable();
            $table->json('rules')->nullable(); // Store structured rules as JSON
            $table->decimal('fee_amount', 18, 2)->nullable(); // e.g., passbook replacement fee
            $table->string('effective_from')->nullable(); // Date policy becomes effective
            $table->string('effective_to')->nullable(); // Date policy ends
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['category', 'is_active']);
            $table->index(['policy_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
