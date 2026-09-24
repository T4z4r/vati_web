<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('religion')->nullable()->after('gender');
            $table->string('permanent_house_number')->nullable()->after('physical_address');
            $table->string('permanent_area')->nullable()->after('permanent_house_number');
            $table->string('permanent_street')->nullable()->after('permanent_area');
            $table->string('permanent_postal_address')->nullable()->after('permanent_street');
            $table->string('permanent_police_station')->nullable()->after('permanent_postal_address');
            $table->string('permanent_district')->nullable()->after('permanent_police_station');
            $table->string('permanent_region')->nullable()->after('permanent_district');
            $table->string('business_work_area')->nullable()->after('street');
            $table->boolean('has_vati_family_member')->default(false)->after('business_work_area');
            $table->string('vati_family_member_name')->nullable()->after('has_vati_family_member');
            $table->boolean('family_member_is_group_member')->default(false)->after('vati_family_member_name');
            $table->string('group_family_member_name')->nullable()->after('family_member_is_group_member');
        });

        Schema::table('member_kycs', function (Blueprint $table) {
            $table->string('current_house_number')->nullable()->after('police_station');
            $table->string('current_area')->nullable()->after('current_house_number');
            $table->string('current_street')->nullable()->after('current_area');
            $table->string('current_postal_address')->nullable()->after('current_street');
            $table->string('current_police_station')->nullable()->after('current_postal_address');
            $table->string('current_district')->nullable()->after('current_police_station');
            $table->string('current_region')->nullable()->after('current_district');
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->date('application_date')->nullable()->after('duration_months');
            $table->date('expected_disbursement_date')->nullable()->after('application_date');
        });

        Schema::table('loan_assessments', function (Blueprint $table) {
            $table->string('external_lender_name')->nullable()->after('existing_external_debt');
            $table->decimal('external_loan_total_amount', 18, 2)->default(0)->after('external_lender_name');
            $table->decimal('external_loan_outstanding_amount', 18, 2)->default(0)->after('external_loan_total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('loan_assessments', function (Blueprint $table) {
            $table->dropColumn(['external_lender_name', 'external_loan_total_amount', 'external_loan_outstanding_amount']);
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn(['application_date', 'expected_disbursement_date']);
        });

        Schema::table('member_kycs', function (Blueprint $table) {
            $table->dropColumn([
                'current_house_number', 'current_area', 'current_street', 'current_postal_address',
                'current_police_station', 'current_district', 'current_region',
            ]);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'religion', 'permanent_house_number', 'permanent_area', 'permanent_street',
                'permanent_postal_address', 'permanent_police_station', 'permanent_district',
                'permanent_region', 'business_work_area', 'has_vati_family_member',
                'vati_family_member_name', 'family_member_is_group_member', 'group_family_member_name',
            ]);
        });
    }
};
