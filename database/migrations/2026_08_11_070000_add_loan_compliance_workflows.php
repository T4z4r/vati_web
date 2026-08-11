<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_terms', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('title');
            $table->longText('body');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->foreignId('loan_term_id')->nullable()->after('business_summary')->constrained('loan_terms')->restrictOnDelete();
            $table->text('consent_declaration')->nullable()->after('loan_term_id');
            $table->timestamp('consented_at')->nullable()->after('consent_declaration');
            $table->string('consented_ip', 45)->nullable()->after('consented_at');
            $table->timestamp('cancellation_deadline')->nullable()->after('consented_ip');
            $table->string('applicant_signature_path')->nullable()->after('cancellation_deadline');
            $table->string('applicant_thumbprint_path')->nullable()->after('applicant_signature_path');
        });

        Schema::table('loan_guarantors', function (Blueprint $table) {
            $table->string('thumbprint_path')->nullable()->after('signature_path');
            $table->string('joint_photo_path')->nullable()->after('thumbprint_path');
            $table->text('declaration_text')->nullable()->after('joint_photo_path');
            $table->timestamp('declaration_accepted_at')->nullable()->after('declaration_text');
        });

        Schema::table('loan_documents', function (Blueprint $table) {
            $table->boolean('is_required')->default(true)->after('document_type');
            $table->text('verification_remarks')->nullable()->after('verification_status');
        });

        Schema::table('member_nominees', function (Blueprint $table) {
            $table->timestamp('attested_at')->nullable()->after('signature_path');
        });

        Schema::create('loan_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->unique()->constrained()->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('cancelled_at');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('passbook_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('reason');
            $table->decimal('fee_amount', 18, 2)->default(1000);
            $table->string('payment_status')->default('pending');
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_default_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('notice_days')->default(14);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->string('delivery_method');
            $table->string('delivery_reference')->nullable();
            $table->text('notice_text');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('loan_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('loan_outstanding_amount', 18, 2)->default(0);
            $table->decimal('security_offset', 18, 2)->default(0);
            $table->decimal('cash_collection', 18, 2)->default(0);
            $table->decimal('security_refund', 18, 2)->default(0);
            $table->text('comments')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->string('manager_signature_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_clearances');
        Schema::dropIfExists('loan_default_notices');
        Schema::dropIfExists('passbook_replacements');
        Schema::dropIfExists('loan_cancellations');

        Schema::table('member_nominees', fn (Blueprint $table) => $table->dropColumn('attested_at'));
        Schema::table('loan_documents', fn (Blueprint $table) => $table->dropColumn(['is_required', 'verification_remarks']));
        Schema::table('loan_guarantors', fn (Blueprint $table) => $table->dropColumn(['thumbprint_path', 'joint_photo_path', 'declaration_text', 'declaration_accepted_at']));
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_term_id');
            $table->dropColumn(['consent_declaration', 'consented_at', 'consented_ip', 'cancellation_deadline', 'applicant_signature_path', 'applicant_thumbprint_path']);
        });
        Schema::dropIfExists('loan_terms');
    }
};
