<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'organization_name', 'value' => 'VATI Microfinance', 'type' => 'string', 'group' => 'general', 'description' => 'Organization name displayed across the system.'],
            ['key' => 'organization_phone', 'value' => '', 'type' => 'string', 'group' => 'general', 'description' => 'Primary contact phone number.'],
            ['key' => 'organization_email', 'value' => '', 'type' => 'string', 'group' => 'general', 'description' => 'Primary contact email address.'],
            ['key' => 'currency', 'value' => 'TZS', 'type' => 'string', 'group' => 'general', 'description' => 'Currency code used for all monetary values.'],
            ['key' => 'currency_symbol', 'value' => 'TZS', 'type' => 'string', 'group' => 'general', 'description' => 'Currency symbol displayed with amounts.'],

            ['key' => 'default_interest_rate', 'value' => '24.00', 'type' => 'number', 'group' => 'loan_defaults', 'description' => 'Default annual interest rate (%) for new loan products.'],
            ['key' => 'default_duration_months', 'value' => '6', 'type' => 'number', 'group' => 'loan_defaults', 'description' => 'Default loan duration in months.'],
            ['key' => 'default_repayment_frequency', 'value' => 'weekly', 'type' => 'string', 'group' => 'loan_defaults', 'description' => 'Default repayment frequency (weekly or monthly).'],
            ['key' => 'min_loan_amount', 'value' => '50000', 'type' => 'number', 'group' => 'loan_defaults', 'description' => 'Minimum loan amount (TZS).'],
            ['key' => 'max_loan_amount', 'value' => '5000000', 'type' => 'number', 'group' => 'loan_defaults', 'description' => 'Maximum loan amount (TZS).'],

            ['key' => 'default_processing_fee', 'value' => '2.50', 'type' => 'number', 'group' => 'fee_defaults', 'description' => 'Default processing fee percentage.'],
            ['key' => 'default_transaction_fee', 'value' => '1.50', 'type' => 'number', 'group' => 'fee_defaults', 'description' => 'Default transaction fee percentage.'],
            ['key' => 'default_security_percentage', 'value' => '10.00', 'type' => 'number', 'group' => 'fee_defaults', 'description' => 'Default security (collateral) percentage of principal.'],
            ['key' => 'default_vat_rate', 'value' => '18.00', 'type' => 'number', 'group' => 'fee_defaults', 'description' => 'Default VAT percentage applied to fees.'],
            ['key' => 'membership_fee', 'value' => '5000', 'type' => 'number', 'group' => 'fee_defaults', 'description' => 'Member admission fee (TZS).'],
            ['key' => 'passbook_replacement_fee', 'value' => '1000', 'type' => 'number', 'group' => 'fee_defaults', 'description' => 'Passbook replacement fee (TZS).'],

            ['key' => 'max_guarantors', 'value' => '2', 'type' => 'number', 'group' => 'business_rules', 'description' => 'Maximum number of guarantors per loan application.'],
            ['key' => 'max_witnesses', 'value' => '10', 'type' => 'number', 'group' => 'business_rules', 'description' => 'Maximum number of group witnesses per application.'],
            ['key' => 'cooling_off_days', 'value' => '3', 'type' => 'number', 'group' => 'business_rules', 'description' => 'Days after consent during which applicant can cancel.'],
            ['key' => 'default_notice_days', 'value' => '14', 'type' => 'number', 'group' => 'business_rules', 'description' => 'Days given in default notice before escalation.'],
            ['key' => 'installment_tolerance', 'value' => '0.009', 'type' => 'number', 'group' => 'business_rules', 'description' => 'Floating-point tolerance for installment balance comparisons.'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
