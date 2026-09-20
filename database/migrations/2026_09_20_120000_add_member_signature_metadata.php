<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_documents', function (Blueprint $table) {
            $table->string('disk')->default('public');
            $table->string('status')->default('uploaded');
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('active_signature_member_id')->nullable()->unique();
            $table->boolean('file_cleanup_pending')->default(false);
        });
        Schema::table('loan_group_witnesses', function (Blueprint $table) {
            $table->foreignId('signature_document_id')->nullable()->constrained('member_documents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_group_witnesses', fn (Blueprint $table) => $table->dropConstrainedForeignId('signature_document_id'));
        Schema::table('member_documents', function (Blueprint $table) {
            $table->dropUnique(['active_signature_member_id']);
            $table->dropColumn(['disk', 'status', 'sha256', 'active_signature_member_id', 'file_cleanup_pending']);
        });
    }
};
