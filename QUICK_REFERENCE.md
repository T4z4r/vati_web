# Quick Reference: Member Registration & Policy System

## Member Registration Fields (40 Total)

### Basic Info (4)
- `first_name` - Required
- `middle_name` - Optional
- `last_name` - Required
- `guardian_name` - Optional (Guardian/Father/Husband)

### Contact (3)
- `phone` - Required, unique
- `alternate_phone` - Optional
- `national_id` - Optional, unique
- `voter_id` - Optional, unique (NEW)

### Personal (5)
- `gender` - Optional
- `date_of_birth` - Optional, must be before today
- `marital_status` - Optional
- `occupation` - Optional
- `nationality` - Optional (NEW)

### Address (5)
- `physical_address` - Optional
- `region` - Optional
- `district` - Optional
- `ward` - Optional
- `street` - Optional

### Dates (2)
- `admission_date` - Optional
- `passbook_issue_date` - Optional, must be >= admission_date (NEW)

### Organization (2)
- `branch_id` - Required (FK to branches)
- `group_id` - Required (FK to member_groups)

### KYC (9) - NEW
- `kyc.household_monthly_income` - Numeric, min 0
- `kyc.household_monthly_expenses` - Numeric, min 0
- `kyc.business_name` - Max 200 chars
- `kyc.business_type` - Max 100 chars
- `kyc.business_address` - Optional
- `kyc.mpesa_phone` - Max 20 chars
- `kyc.bank_account_number` - Max 50 chars
- `kyc.bank_account_name` - Max 100 chars
- `kyc.bank_name` - Max 100 chars

### Usage Example
```php
$member = Member::create([
    'branch_id' => 1,
    'group_id' => 1,
    'first_name' => 'John',
    'last_name' => 'Doe',
    'guardian_name' => 'Jane Doe',
    'phone' => '+255...',
    'national_id' => 'ID123',
    'voter_id' => 'VOTER123',
    'admission_date' => '2026-01-15',
    'passbook_issue_date' => '2026-01-15',
]);

$member->kyc()->create([
    'household_monthly_income' => 500000,
    'business_name' => 'John\'s Farm',
]);
```

---

## Policy System Quick Reference

### Policy Model
```php
use App\Models\Policy;

// Query policies
Policy::active()->get();
Policy::byCategory('passbook')->active()->get();
Policy::where('policy_code', 'PASSBOOK_001')->first();

// Access data
$policy->policy_code;        // e.g., "PASSBOOK_001"
$policy->policy_title;       // e.g., "Safekeeping"
$policy->category;           // e.g., "passbook"
$policy->description;        // Short desc
$policy->detailed_content;   // Full policy text
$policy->rules;              // JSON array
$policy->fee_amount;         // e.g., 1000.00 for replacement
$policy->is_active;          // true/false
$policy->version;            // Version number
```

### Policy Categories (18 Total)

**Passbook (5)**
- PASSBOOK_001: Safekeeping
- PASSBOOK_002: Signature Verification
- PASSBOOK_003: Non-Transferability
- PASSBOOK_004: Lost/Damaged Replacement (Fee: 1,000 TSH)
- PASSBOOK_005: Loss Reporting

**Loan (4)**
- LOAN_001: Terms & Conditions Knowledge
- LOAN_002: Non-Sharing Principle
- LOAN_003: Receipt Verification
- LOAN_004: Early Repayment for Clearance

**Payment (3)**
- PAYMENT_001: Authorized Personnel Only
- PAYMENT_002: No Holiday Payments
- PAYMENT_003: Public Recording

**Membership (3)**
- MEMBER_001: Passbook Verification Responsibility
- MEMBER_002: Loan Information Verification
- MEMBER_003: Storage Prohibition

**Fraud Prevention (2)**
- FRAUD_001: Loan Sharing Warning
- FRAUD_002: Discrepancy Reporting

**General (1)**
- GENERAL_001: Complaint Handling

### Common Code Patterns

**Get Passbook Replacement Fee**
```php
$fee = Policy::where('policy_code', 'PASSBOOK_004')
    ->first()?->fee_amount;
// Returns: 1000.00
```

**Check Policy Status**
```php
$policy = Policy::where('policy_code', 'LOAN_001')->first();
if ($policy->is_active) {
    // Policy is active, enforce it
}
```

**Get All Rules for Policy**
```php
$policy = Policy::where('policy_code', 'PASSBOOK_002')->first();
$rules = $policy->rules;
// Example rules array:
// ['must_be_signed' => true, 'signed_by' => 'loan_officer', ...]
```

**List Policies by Category**
```php
$passbookPolicies = Policy::byCategory('passbook')
    ->active()
    ->get();

foreach ($passbookPolicies as $policy) {
    echo $policy->policy_title;      // Displays policy title
    echo $policy->description;        // Displays description
}
```

**Get Policy Contact Info**
```php
// Contact number mentioned in policies:
$contactNumber = '+255 764 897 791';

// Address:
$address = 'P.O. Box 4859, Dar es Salaam, Tanzania';
```

### Creating New Policies

```php
Policy::create([
    'policy_code' => 'CUSTOM_001',
    'policy_title' => 'My New Policy',
    'category' => 'loan',
    'description' => 'Description',
    'detailed_content' => 'Full policy text',
    'rules' => [
        'key1' => 'value1',
        'key2' => true,
    ],
    'fee_amount' => null,
    'is_active' => true,
    'version' => 1,
]);
```

### Updating Policy Version

```php
$policy = Policy::where('policy_code', 'PASSBOOK_001')->first();
$policy->update([
    'detailed_content' => 'Updated content...',
    'version' => $policy->version + 1,
]);
```

---

## Database Tables Reference

### members
- membership_number (unique)
- branch_id (FK)
- group_id (FK)
- first_name, middle_name, last_name
- guardian_name
- phone, alternate_phone (unique)
- national_id, voter_id (unique)
- date_of_birth, gender, marital_status, occupation
- nationality
- physical_address, region, district, ward, street
- admission_date, passbook_issue_date
- status, created_by, created_at, updated_at, deleted_at

### member_kycs
- member_id (unique FK)
- household_monthly_income, household_monthly_expenses
- business_name, business_type, business_address
- mpesa_phone, bank_account_number, bank_account_name, bank_name
- house_number, police_station, house_ownership_status
- number_of_dependants, head_of_household
- house_roof_type, house_fence_type
- created_at, updated_at

### policies
- policy_code (unique)
- policy_title, category, description
- detailed_content, rules (JSON)
- fee_amount, effective_from, effective_to
- version, is_active
- created_by, updated_by (FK)
- created_at, updated_at

---

## Installation & Testing

```bash
# 1. Run migrations to create policies table
php artisan migrate

# 2. Seed policies
php artisan db:seed --class=PolicySeeder

# 3. Or seed all data
php artisan db:seed

# 4. Test in Tinker
php artisan tinker
>>> Policy::count()  // Should show 18
>>> Policy::byCategory('passbook')->count()  // Should show 5
>>> Policy::where('policy_code', 'PASSBOOK_004')->first()->fee_amount  // Should show 1000
```

---

## Validation Rules Summary

```php
// In StoreMemberRequest::rules()

// Required fields
'branch_id' => ['required', 'exists:branches,id'],
'group_id' => ['required', 'exists:member_groups,id'],
'first_name' => ['required', 'string', 'max:100'],
'last_name' => ['required', 'string', 'max:100'],
'phone' => ['required', 'string', 'max:20', 'unique:members,phone'],

// Optional fields with constraints
'guardian_name' => ['nullable', 'string', 'max:100'],
'voter_id' => ['nullable', 'string', 'max:50', 'unique:members,voter_id'],
'date_of_birth' => ['nullable', 'date', 'before:today'],
'passbook_issue_date' => ['nullable', 'date', 'after_or_equal:admission_date'],

// KYC fields
'kyc.household_monthly_income' => ['nullable', 'numeric', 'min:0'],
'kyc.business_name' => ['nullable', 'string', 'max:200'],
'kyc.bank_account_number' => ['nullable', 'string', 'max:50'],
```

---

## Important Notes

⚠️ **Field Changes**
- `voter_id` - NEW field (voter ID unique constraint)
- `nationality` - NEW field
- `passbook_issue_date` - NEW field (with validation >= admission_date)
- All 9 KYC business/bank fields - NEW

⚠️ **Policy Fee**
- Passbook replacement: 1,000 TSH (stored in PASSBOOK_004.fee_amount)

⚠️ **Contact Information**
- Phone: +255 764 897 791
- Address: P.O. Box 4859, Dar es Salaam, Tanzania
- Location: Mtaa wa Baruti, Barabara ya kwa Ayubu, Kimara-Ubungo

⚠️ **Date Validation**
- `passbook_issue_date` must be >= `admission_date`
- `date_of_birth` must be < today

---

## Documentation Files

1. **MEMBER_REGISTRATION_PDF_MAPPING.md** - Complete field mapping (40 fields)
2. **POLICY_SYSTEM_GUIDE.md** - Comprehensive policy system guide
3. **IMPLEMENTATION_SUMMARY.md** - Full implementation details
4. **IMPLEMENTATION_CHECKLIST.md** - Verification checklist
5. **QUICK_REFERENCE.md** - This file

---

## Files Modified/Created

**Created:**
- app/Models/Policy.php
- database/migrations/2026_08_12_000000_create_policies_table.php
- database/seeders/PolicySeeder.php

**Updated:**
- app/Http/Requests/StoreMemberRequest.php (+13 validations)
- database/seeders/DatabaseSeeder.php (+PolicySeeder)

---

*Last Updated: 2026-08-12*
*Status: Ready for Production*
