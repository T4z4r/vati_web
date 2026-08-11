<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('branch_code')->unique();
            $table->string('branch_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('status')->default(true)->after('password');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_number')->unique();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->date('joined_at')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('member_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('group_code')->unique();
            $table->string('group_name');
            $table->string('meeting_day')->nullable();
            $table->time('meeting_time')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('ward')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('loan_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('membership_number')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained('member_groups')->restrictOnDelete();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('guardian_name')->nullable();
            $table->string('phone')->unique();
            $table->string('alternate_phone')->nullable();
            $table->string('national_id')->nullable()->unique();
            $table->string('voter_id')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('occupation')->nullable();
            $table->string('nationality')->default('Tanzanian');
            $table->text('physical_address')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('ward')->nullable();
            $table->string('street')->nullable();
            $table->date('admission_date')->nullable();
            $table->date('passbook_issue_date')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'group_id', 'status']);
        });

        Schema::create('group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained('member_groups')->restrictOnDelete();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['member_id', 'status']);
            $table->index(['group_id', 'status']);
        });

        Schema::create('member_kycs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mpesa_phone')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('house_number')->nullable();
            $table->string('police_station')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_type')->nullable();
            $table->text('business_address')->nullable();
            $table->decimal('household_monthly_income', 18, 2)->default(0);
            $table->decimal('household_monthly_expenses', 18, 2)->default(0);
            $table->unsignedInteger('number_of_dependants')->default(0);
            $table->string('head_of_household')->nullable();
            $table->string('house_ownership_status')->nullable();
            $table->string('house_roof_type')->nullable();
            $table->string('house_fence_type')->nullable();
            $table->timestamps();
        });

        Schema::create('member_family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gender')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('relationship')->nullable();
            $table->string('education')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('occupation')->nullable();
            $table->string('secondary_occupation')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('member_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('estimated_value', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('member_nominees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('signature_path')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('minimum_amount', 18, 2)->default(0);
            $table->decimal('maximum_amount', 18, 2)->default(0);
            $table->unsignedInteger('minimum_duration_months')->default(1);
            $table->unsignedInteger('maximum_duration_months')->default(12);
            $table->decimal('annual_interest_rate', 8, 4)->default(0);
            $table->string('interest_method')->default('reducing_balance');
            $table->string('repayment_frequency')->default('weekly');
            $table->decimal('security_percentage', 8, 4)->default(0);
            $table->decimal('processing_fee_percentage', 8, 4)->default(0);
            $table->decimal('transaction_fee_percentage', 8, 4)->default(0);
            $table->decimal('membership_fee', 18, 2)->default(0);
            $table->decimal('vat_percentage', 8, 4)->default(18);
            $table->unsignedInteger('required_group_witnesses')->default(2);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained('member_groups')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('application_type')->default('main');
            $table->decimal('requested_amount', 18, 2);
            $table->unsignedInteger('duration_months');
            $table->decimal('existing_loan_balance', 18, 2)->default(0);
            $table->decimal('refinancing_amount', 18, 2)->default(0);
            $table->decimal('increment_amount', 18, 2)->default(0);
            $table->text('loan_purpose')->nullable();
            $table->text('business_summary')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('loan_utilizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('purpose');
            $table->decimal('allocation_amount', 18, 2)->default(0);
            $table->decimal('current_asset_value', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('loan_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('core_business_income', 18, 2)->default(0);
            $table->decimal('other_income', 18, 2)->default(0);
            $table->decimal('business_expenses', 18, 2)->default(0);
            $table->decimal('household_expenses', 18, 2)->default(0);
            $table->decimal('monthly_profit', 18, 2)->default(0);
            $table->decimal('disposable_income', 18, 2)->default(0);
            $table->decimal('existing_external_debt', 18, 2)->default(0);
            $table->decimal('debt_service_ratio', 8, 4)->nullable();
            $table->decimal('affordability_score', 8, 4)->nullable();
            $table->text('assessment_comment')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('guarantor_type')->nullable();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone')->nullable();
            $table->string('national_id')->nullable();
            $table->string('voter_id')->nullable();
            $table->string('house_number')->nullable();
            $table->string('street')->nullable();
            $table->string('ward')->nullable();
            $table->string('district')->nullable();
            $table->string('region')->nullable();
            $table->text('business_address')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('file_path');
            $table->string('verification_status')->default('pending');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_group_witnesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('member_groups')->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('signature_path')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['loan_application_id', 'member_id']);
            $table->index(['group_id', 'confirmed_at']);
        });

        Schema::create('loan_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->string('decision');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique();
            $table->foreignId('loan_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained('member_groups')->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('principal_amount', 18, 2);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('total_repayment', 18, 2);
            $table->decimal('principal_balance', 18, 2);
            $table->decimal('interest_balance', 18, 2)->default(0);
            $table->decimal('total_balance', 18, 2);
            $table->unsignedInteger('number_of_installments')->default(0);
            $table->decimal('installment_amount', 18, 2)->default(0);
            $table->date('disbursement_date')->nullable();
            $table->date('first_payment_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('status')->default('pending_disbursement')->index();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('method');
            $table->string('recipient_number')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('provider_reference')->nullable();
            $table->dateTime('disbursed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date')->index();
            $table->decimal('principal_due', 18, 2)->default(0);
            $table->decimal('interest_due', 18, 2)->default(0);
            $table->decimal('total_due', 18, 2)->default(0);
            $table->decimal('principal_paid', 18, 2)->default(0);
            $table->decimal('interest_paid', 18, 2)->default(0);
            $table->decimal('total_paid', 18, 2)->default(0);
            $table->decimal('interest_exemption', 18, 2)->default(0);
            $table->decimal('outstanding_balance', 18, 2)->default(0);
            $table->string('status')->default('upcoming')->index();
            $table->timestamps();
            $table->unique(['loan_id', 'installment_number']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('payment_number')->unique();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('payment_method');
            $table->string('reference_number')->nullable();
            $table->string('external_reference')->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();
            $table->timestamp('client_created_at')->nullable();
            $table->timestamp('server_received_at')->nullable();
            $table->string('sync_status')->default('synced');
            $table->text('remarks')->nullable();
            $table->string('status')->default('posted')->index();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_installment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('member_security_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('security_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('member_security_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type');
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_before', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transaction_date');
            $table->timestamps();
        });

        Schema::create('loan_refinancings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('old_loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignId('new_loan_id')->constrained('loans')->restrictOnDelete();
            $table->decimal('old_outstanding_balance', 18, 2);
            $table->decimal('new_principal_amount', 18, 2);
            $table->decimal('net_disbursement_amount', 18, 2);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at');
            $table->timestamps();
        });

        Schema::create('loan_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->unique();
            $table->foreignId('loan_id')->unique()->constrained()->restrictOnDelete();
            $table->date('settlement_date');
            $table->decimal('principal_outstanding', 18, 2);
            $table->decimal('interest_outstanding', 18, 2);
            $table->decimal('interest_waived', 18, 2)->default(0);
            $table->decimal('security_offset', 18, 2)->default(0);
            $table->decimal('cash_payment', 18, 2)->default(0);
            $table->decimal('security_refund', 18, 2)->default(0);
            $table->decimal('final_balance', 18, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('group_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('member_groups')->cascadeOnDelete();
            $table->date('meeting_date');
            $table->foreignId('loan_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('group_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('present');
            $table->timestamps();
            $table->unique(['group_meeting_id', 'member_id']);
        });

        Schema::create('group_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('member_groups')->restrictOnDelete();
            $table->foreignId('group_meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->date('collection_date');
            $table->decimal('expected_amount', 18, 2)->default(0);
            $table->decimal('collected_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0);
            $table->foreignId('loan_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('transaction_type');
            $table->string('reference')->unique();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('external_reference')->nullable();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('phone')->nullable();
            $table->string('status')->default('pending');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('push_token')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'device_uuid']);
        });

        Schema::create('cashbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('closing_balance', 18, 2)->default(0);
            $table->string('status')->default('open');
            $table->timestamps();
            $table->unique(['branch_id', 'business_date']);
        });

        Schema::create('cashbook_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashbook_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type');
            $table->string('direction');
            $table->decimal('amount', 18, 2);
            $table->nullableMorphs('source');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = [
            'cashbook_transactions', 'cashbooks', 'user_devices', 'payment_transactions',
            'group_collections', 'group_attendances', 'group_meetings', 'loan_settlements',
            'loan_refinancings', 'security_transactions', 'member_security_accounts',
            'payment_allocations', 'payments', 'loan_installments', 'loan_disbursements',
            'loans', 'loan_approvals', 'loan_group_witnesses', 'loan_documents', 'loan_guarantors', 'loan_assessments',
            'loan_utilizations', 'loan_applications', 'loan_products', 'member_nominees',
            'member_assets', 'asset_types', 'member_family_members', 'member_kycs',
            'group_memberships', 'members', 'member_groups', 'employees',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('status');
        });

        Schema::dropIfExists('branches');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('regions');
    }
};
