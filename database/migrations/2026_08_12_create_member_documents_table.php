<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('document_type'); // national_id, voter_id, address_proof, business_license, passbook_scan, etc.
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable(); // in bytes
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['member_id', 'document_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_documents');
    }
};
