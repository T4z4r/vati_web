<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->foreignId('assigned_credit_officer_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->decimal('recommended_amount', 18, 2)->nullable()->after('requested_amount');
            $table->unsignedInteger('recommended_duration_months')->nullable()->after('duration_months');
            $table->string('risk_level')->nullable()->after('business_summary');
            $table->unsignedInteger('credit_review_attempt')->default(1)->after('risk_level');
        });

        Schema::create('credit_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt')->default(1);
            $table->string('decision');
            $table->decimal('recommended_amount', 18, 2)->nullable();
            $table->unsignedInteger('recommended_duration_months')->nullable();
            $table->string('overall_risk');
            $table->text('remarks')->nullable();
            $table->boolean('member_verified')->default(false);
            $table->boolean('group_membership_verified')->default(false);
            $table->boolean('documents_verified')->default(false);
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->unique(['loan_application_id', 'attempt']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('passbook_issue_date');
        });

        Schema::table('loan_documents', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('file_path');
            $table->string('mime_type')->nullable()->after('original_name');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->text('remarks')->nullable()->after('size_bytes');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('loan_documents', fn (Blueprint $table) => $table->dropColumn(['original_name', 'mime_type', 'size_bytes', 'remarks']));
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('photo_path'));
        Schema::dropIfExists('credit_reviews');
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_credit_officer_id');
            $table->dropColumn(['recommended_amount', 'recommended_duration_months', 'risk_level', 'credit_review_attempt']);
        });
    }
};
