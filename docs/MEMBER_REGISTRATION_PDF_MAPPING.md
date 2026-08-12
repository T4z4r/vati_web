# Member Registration PDF Field Mapping

This document maps all fields from the VATI Microfinance Member's Passbook (Kitabu cha Marejesho ya Mteja) PDF to the database schema and member registration form.

## PDF Fields Mapping

### Member Identification Section

| PDF Field (Swahili) | PDF Field (English) | Database Field | Form Field | Status |
|---|---|---|---|---|
| Namba ya Mwanachama | Membership/SL Number | `membership_number` | Auto-generated | ✓ Captured |
| Jina la Mwanachama | Name of Member | `first_name`, `middle_name`, `last_name` | name fields | ✓ Captured |
| Jina la Mlezi/Baba/Mume | Guardian/Father/Husband Name | `guardian_name` | guardian_name | ✓ Captured |

### Branch & Group Information

| PDF Field (Swahili) | PDF Field (English) | Database Field | Form Field | Status |
|---|---|---|---|---|
| Jina la Tawi | Name of Branch | `branches.branch_name` (via FK) | branch_id | ✓ Captured |
| Siku ya Kukutana | Meeting day | `member_groups.meeting_day` (via FK) | group_id | ✓ Captured |
| Jina la Kikundi | Group Name | `member_groups.group_name` (via FK) | group_id | ✓ Captured |
| Mahali Kikundi kilipo | Location/Place of Group | `member_groups.location` (via FK) | group_id | ✓ Captured |

### Contact Information

| PDF Field (Swahili) | PDF Field (English) | Database Field | Form Field | Status |
|---|---|---|---|---|
| Namba ya simu ya Mwanachama | Member Contact Number | `phone` | phone | ✓ Captured |
| - | Alternate Phone | `alternate_phone` | alternate_phone | ✓ Captured |

### Identification Documents

| PDF Field (Swahili) | PDF Field (English) | Database Field | Form Field | Status |
|---|---|---|---|---|
| Kitambulisho cha Taifa | National ID (If Any) | `national_id` | national_id | ✓ Captured |
| - | Voter ID | `voter_id` | voter_id | ✓ Captured |

### Personal Information

| PDF Field (Swahili) | PDF Field (English) | Database Field | Form Field | Status |
|---|---|---|---|---|
| - | Date of Birth | `date_of_birth` | date_of_birth | ✓ Captured |
| - | Gender | `gender` | gender | ✓ Captured |
| - | Marital Status | `marital_status` | marital_status | ✓ Captured |
| - | Occupation | `occupation` | occupation | ✓ Captured |
| - | Nationality | `nationality` | nationality | ✓ Captured |

### Address Information

| PDF Field (Swahili) | PDF Field (English) | Database Field | Form Field | Status |
|---|---|---|---|---|
| Anuani ya makazi | Physical Address | `physical_address` | physical_address | ✓ Captured |
| - | Region | `region` | region | ✓ Captured |
| - | District | `district` | district | ✓ Captured |
| - | Ward | `ward` | ward | ✓ Captured |
| - | Street | `street` | street | ✓ Captured |

### Dates & Passbook Information

| PDF Field (Swahili) | PDF Field (English) | Database Field | Form Field | Status |
|---|---|---|---|---|
| Tarehe ya kujiunga | Admission Date | `admission_date` | admission_date | ✓ Captured |
| Tarehe ya kutoa Kitabu cha marejesho | Passbook Issue Date | `passbook_issue_date` | passbook_issue_date | ✓ Captured |

### KYC (Know Your Customer) Information

| PDF Field (Swahili) | PDF Field (English) | Database Field | Form Field | Status |
|---|---|---|---|---|
| - | Household Monthly Income | `member_kycs.household_monthly_income` | kyc.household_monthly_income | ✓ Captured |
| - | Household Monthly Expenses | `member_kycs.household_monthly_expenses` | kyc.household_monthly_expenses | ✓ Captured |
| - | Business Name | `member_kycs.business_name` | kyc.business_name | ✓ Captured |
| - | Business Type | `member_kycs.business_type` | kyc.business_type | ✓ Captured |
| - | Business Address | `member_kycs.business_address` | kyc.business_address | ✓ Captured |
| - | M-Pesa Phone | `member_kycs.mpesa_phone` | kyc.mpesa_phone | ✓ Captured |
| - | Bank Account Number | `member_kycs.bank_account_number` | kyc.bank_account_number | ✓ Captured |
| - | Bank Account Name | `member_kycs.bank_account_name` | kyc.bank_account_name | ✓ Captured |
| - | Bank Name | `member_kycs.bank_name` | kyc.bank_name | ✓ Captured |

## Summary

✓ **All PDF fields are captured** in the member registration system with the following structure:

1. **Member Model** - Stores core member information
2. **MemberGroup Model** - Stores group information including meeting day and location
3. **MemberKyc Model** - Stores financial and business information
4. **Branch & Area Models** - Store organizational hierarchy

## Passbook-Related Fields

The passbook document captures loan information which is stored separately:
- **Loan Cycles** (Main/Refinancing) - Stored in `loans` table
- **Loan Products** - Stored in `loan_products` table
- **Loan Terms** - Stored in `loan_terms` table
- **Payments & Installments** - Stored in `payments` and `loan_installments` tables

## Policy Enforcement

The following policies are enforced through the `policies` table:

### Passbook Policies
- PASSBOOK_001: Safekeeping
- PASSBOOK_002: Signature Verification
- PASSBOOK_003: Non-Transferability
- PASSBOOK_004: Replacement with Fee (1,000 TSH)
- PASSBOOK_005: Loss Reporting

### Loan Policies
- LOAN_001: Terms & Conditions Knowledge
- LOAN_002: Non-Sharing Principle
- LOAN_003: Receipt Verification
- LOAN_004: Early Repayment for Clearance

### Payment Policies
- PAYMENT_001: Authorized Personnel Only
- PAYMENT_002: No Holiday Payments
- PAYMENT_003: Public Recording

### Membership Policies
- MEMBER_001: Passbook Verification Responsibility
- MEMBER_002: Loan Information Verification
- MEMBER_003: Storage Prohibition

### Fraud Prevention
- FRAUD_001: Loan Sharing Warning
- FRAUD_002: Discrepancy Reporting

## Installation

To run the migrations and seeders:

```bash
php artisan migrate
php artisan db:seed
```

This will:
1. Create the `policies` table
2. Populate all member-related tables
3. Seed all business policies from the passbook requirements
