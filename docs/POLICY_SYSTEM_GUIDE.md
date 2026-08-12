# Policy System Implementation Guide

## Overview

The Policy System provides a centralized way to manage and enforce VATI Microfinance business rules, terms, and conditions captured in the Member's Passbook and other regulatory documents.

## Policy Model

Located in: `app/Models/Policy.php`

### Fields

- **id** - Primary key
- **policy_code** - Unique identifier (e.g., PASSBOOK_001)
- **policy_title** - Human-readable policy name
- **category** - Policy type (passbook, loan, membership, payment, etc.)
- **description** - Short description
- **detailed_content** - Full policy text (supports translations)
- **rules** - JSON-structured rules for programmatic enforcement
- **fee_amount** - Associated fees (e.g., passbook replacement: 1,000 TSH)
- **effective_from** - Policy start date
- **effective_to** - Policy end date
- **version** - Version number for tracking changes
- **is_active** - Boolean flag for activation/deactivation

## Policy Categories

### 1. Passbook Policies (PASSBOOK_*)
Related to member passbook handling, safekeeping, and maintenance.

**Policies:**
- PASSBOOK_001: Safekeeping - Keep passbook carefully
- PASSBOOK_002: Signature Verification - Officer must sign after each payment
- PASSBOOK_003: Non-Transferability - Cannot be transferred to anyone else
- PASSBOOK_004: Lost/Damaged Replacement - 1,000 TSH fee applies
- PASSBOOK_005: Loss Reporting - Must contact loan officer immediately

### 2. Loan Policies (LOAN_*)
Related to loan origination, management, and repayment.

**Policies:**
- LOAN_001: Terms Knowledge - Must understand conditions before disbursement
- LOAN_002: Non-Sharing - Cannot share loan with employees or others
- LOAN_003: Receipt Verification - Verify full amount at disbursement
- LOAN_004: Early Repayment - Allowed for loan clearance and next loan eligibility

### 3. Payment Policies (PAYMENT_*)
Related to payment collection and processing.

**Policies:**
- PAYMENT_001: Authorized Personnel Only - Only authorized staff collect payments
- PAYMENT_002: No Holiday Payments - No collection on public holidays
- PAYMENT_003: Public Recording - Early payments recorded at group meetings

### 4. Membership Policies (MEMBER_*)
Related to member responsibilities and obligations.

**Policies:**
- MEMBER_001: Passbook Verification - Verify after each transaction
- MEMBER_002: Loan Information Verification - Cross-check balance and details
- MEMBER_003: Storage Prohibition - Don't keep passbook at meeting venue

### 5. Fraud Prevention (FRAUD_*)
Related to fraud prevention and reporting.

**Policies:**
- FRAUD_001: Loan Sharing Warning - Report unauthorized sharing attempts
- FRAUD_002: Discrepancy Reporting - Report missing or wrong amounts

### 6. General Policies (GENERAL_*)
General operational policies.

**Policies:**
- GENERAL_001: Complaint Handling - Multiple complaint channels available

## Using the Policy System

### Retrieving Policies

```php
use App\Models\Policy;

// Get all active policies
$policies = Policy::active()->get();

// Get policies by category
$passbookPolicies = Policy::byCategory('passbook')->active()->get();

// Get specific policy
$policy = Policy::where('policy_code', 'PASSBOOK_001')->first();

// Get all passbook replacement policies with fee
$replacementFee = Policy::where('policy_code', 'PASSBOOK_004')->first()?->fee_amount;
```

### Accessing Policy Rules

```php
// Get the JSON rules
$policy = Policy::where('policy_code', 'PASSBOOK_002')->first();
$rules = $policy->rules; // Returns array

// Check if signature required
$mustSign = $rules['must_be_signed'] ?? false;
```

### Creating New Policies

```php
Policy::create([
    'policy_code' => 'NEW_001',
    'policy_title' => 'New Policy',
    'category' => 'membership',
    'description' => 'Description',
    'detailed_content' => 'Full content',
    'rules' => [
        'rule_key' => 'rule_value',
    ],
    'is_active' => true,
]);
```

## Policy Enforcement in Code

### Example: Passbook Replacement

```php
// In PassbookReplacementController
use App\Models\Policy;

$replacementPolicy = Policy::where('policy_code', 'PASSBOOK_004')->first();
$fee = $replacementPolicy->fee_amount; // 1000.00 TSH

// Charge the member
$member->payFee($fee, 'passbook_replacement');

// Create replacement
$member->passbookReplacements()->create([
    'reason' => 'damaged',
    'fee_charged' => $fee,
]);
```

### Example: Payment Validation

```php
// In PaymentController
use App\Models\Policy;

$authorizedPolicy = Policy::where('policy_code', 'PAYMENT_001')->first();
if (!$this->isAuthorizedPersonnel($user)) {
    return error('Only authorized personnel can collect payments');
}

$holidayPolicy = Policy::where('policy_code', 'PAYMENT_002')->first();
if ($this->isPublicHoliday(today())) {
    return error('No payments accepted on holidays');
}
```

## Displaying Policies to Members

### In Member Portal/Dashboard

```php
// Get all active policies for display
$allPolicies = Policy::active()->get()->groupBy('category');

// Display by category
foreach ($allPolicies as $category => $policies) {
    echo "<h3>$category Policies</h3>";
    foreach ($policies as $policy) {
        echo "<p><strong>{$policy->policy_title}</strong></p>";
        echo "<p>{$policy->description}</p>";
    }
}
```

## Policy Versioning

Policies are versioned to track changes over time:

```php
// Update policy version when changing terms
$policy = Policy::where('policy_code', 'PASSBOOK_001')->first();
$policy->update([
    'detailed_content' => 'New terms...',
    'version' => $policy->version + 1,
]);
```

## Reporting & Compliance

### Policy Compliance Report

```php
// Check all active policies
$policies = Policy::active()->get();
foreach ($policies as $policy) {
    echo "Policy: {$policy->policy_code} v{$policy->version}\n";
    echo "Category: {$policy->category}\n";
    echo "Status: " . ($policy->is_active ? 'Active' : 'Inactive') . "\n";
    echo "---\n";
}
```

## Migration & Seeding

Run the seeder to populate all policies:

```bash
php artisan db:seed --class=PolicySeeder
```

Or as part of full database setup:

```bash
php artisan migrate
php artisan db:seed
```

## Adding New Policies

1. Create a new policy code following the pattern: `CATEGORY_###`
2. Add it to `PolicySeeder.php` in the `run()` method
3. Run `php artisan db:seed --class=PolicySeeder`

Example:

```php
// In PolicySeeder::run()
Policy::updateOrCreate(
    ['policy_code' => 'NEW_CATEGORY_001'],
    [
        'policy_title' => 'New Policy Title',
        'category' => 'new_category',
        'description' => 'Description of the policy',
        'detailed_content' => 'Full policy content',
        'rules' => [
            'key1' => 'value1',
            'key2' => true,
        ],
        'is_active' => true,
    ]
);
```

## Contact Information in Policies

Policies maintain VATI's contact details for member inquiries:

- **Phone**: +255 764 897 791
- **Address**: P.O. Box 4859, Dar es Salaam, Tanzania
- **Location**: Mtaa wa Baruti, Barabara ya kwa Ayubu, Kimara-Ubungo, Dar es Salaam

These are referenced in:
- PAYMENT_001: Authorized Personnel Verification
- FRAUD_001: Loan Sharing Reports
- FRAUD_002: Discrepancy Reports
- GENERAL_001: Complaint Handling

## Key Takeaways

1. **Centralized Management**: All policies stored in one table
2. **Easy Updates**: Change policy terms without code changes
3. **Version Tracking**: Know which version of policy applies
4. **Flexible Rules**: JSON rules for complex business logic
5. **Fee Management**: Store associated fees (e.g., 1,000 TSH replacement)
6. **Multi-language Support**: `detailed_content` supports translations
7. **Audit Trail**: Created/updated timestamps for compliance
