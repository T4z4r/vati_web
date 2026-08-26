# VATI Loan Processing Flow

> Complete reference for mobile app implementation. Covers every status, API endpoint, business rule, and data model from application creation through settlement.

---

## Table of Contents

1. [Status Enums](#1-status-enums)
2. [Numbering Formats](#2-numbering-formats)
3. [Loan Product Configuration](#3-loan-product-configuration)
4. [Phase 1: Application Creation](#4-phase-1-application-creation)
5. [Phase 2: Compliance Evidence Collection](#5-phase-2-compliance-evidence-collection)
6. [Phase 3: Submission](#6-phase-3-submission)
7. [Phase 4: Credit Review](#7-phase-4-credit-review)
8. [Phase 5: Approval](#8-phase-5-approval)
9. [Phase 6: Cooling-Off Period](#9-phase-6-cooling-off-period)
10. [Phase 7: Disbursement](#10-phase-7-disbursement)
11. [Phase 8: Repayment](#11-phase-8-repayment)
12. [Phase 9: Settlement & Clearance](#12-phase-9-settlement--clearance)
13. [Phase 10: Administration](#13-phase-10-administration)
14. [Loan Calculator](#14-loan-calculator)
15. [Repayment Schedule Generation](#15-repayment-schedule-generation)
16. [Payment Allocation Algorithm](#16-payment-allocation-algorithm)
17. [Security (Collateral) Accounts](#17-security-collateral-accounts)
18. [Portfolio Analytics](#18-portfolio-analytics)
19. [Complete API Reference](#19-complete-api-reference)
20. [Database Schema](#20-database-schema)
21. [Notifications](#21-notifications)
22. [Business Constants](#22-business-constants)

---

## 1. Status Enums

### Application Status (`ApplicationStatus`)

| Value | Description |
|---|---|
| `draft` | Initial state after creation. Editable. |
| `submitted` | Applicant has submitted. Awaiting review. |
| `lo_review` | Loan Officer review stage. |
| `abm_review` | Assistant Branch Manager review stage. |
| `bm_review` | Branch Manager review stage. |
| `credit_review` | Credit officer actively reviewing. |
| `recommended` | Credit officer has recommended. Awaiting admin approval. |
| `approved` | Approved. Ready for disbursement after cooling-off. |
| `rejected` | Rejected. Terminal state. |
| `returned` | Returned to applicant for correction. Re-submittable. |
| `cancelled` | Applicant cancelled during cooling-off. Terminal state. |
| `disbursement_pending` | Approved and awaiting disbursement execution. |
| `disbursed` | Funds released. Terminal for application. |

### Loan Status (`LoanStatus`)

| Value | Description |
|---|---|
| `pending_disbursement` | Loan record created upon approval. Awaiting disbursement. |
| `active` | Disbursed and in repayment. |
| `overdue` | Has installments past due date with outstanding balance. |
| `settled` | Fully paid or settled. Terminal state. |
| `refinanced` | Closed via refinancing into a new loan. |
| `written_off` | Written off as bad debt. |
| `cancelled` | Cancelled before disbursement. |

### Installment Status (`loan_installments.status`)

| Value | Description |
|---|---|
| `upcoming` | Due date not yet reached. |
| `due` | Due date reached, not yet paid. |
| `partially_paid` | Some amount paid but not fully cleared. |
| `overdue` | Past due date with outstanding balance. |
| `paid` | Fully paid. |
| `waived` | Interest exempted (via interest_exemption field). |

### Payment Status (`payments.status`)

| Value | Description |
|---|---|
| `posted` | Payment recorded and allocated. |
| `reversed` | Payment reversed. Terminal state. |

### Clearance Status (`loan_clearances.status`)

| Value | Description |
|---|---|
| `pending` | Clearance record created at settlement. Awaiting authorization. |
| `authorized` | Branch manager has signed off. |

---

## 2. Numbering Formats

| Entity | Pattern | Example |
|---|---|---|
| Member | `VATI-M-YYYY-NNNNNN` | `VATI-M-2026-000123` |
| Group | `VATI-GRP-YYYY-NNNNNN` | `VATI-GRP-2026-000045` |
| Loan Application | `VATI-LAF-YYYY-NNNNNN` | `VATI-LAF-2026-000078` |
| Loan | `VATI-LN-YYYY-NNNNNN` | `VATI-LN-2026-000012` |
| Payment | `VATI-PAY-YYYY-NNNNNN` | `VATI-PAY-2026-000345` |
| Settlement | `VATI-STL-YYYY-NNNNNN` | `VATI-STL-2026-000003` |
| Security Transaction | `VATI-SEC-YYYY-NNNNNN` | `VATI-SEC-2026-000067` |

---

## 3. Loan Product Configuration

Each `LoanProduct` defines the lending rules:

```
name, code
minimum_amount, maximum_amount          (TZS)
minimum_duration_months, maximum_duration_months
annual_interest_rate                     (e.g. 24.0000 = 24%)
interest_method                          ("reducing_balance" — note: calculator uses flat rate)
repayment_frequency                      ("weekly" or "monthly")
security_percentage                      (% of principal held as collateral)
processing_fee_percentage                (% of principal)
insurance_percentage                     (% of principal, e.g. 1.5%)
transaction_fee_percentage               (legacy, unused — always 0)
membership_fee                           (legacy, unused — always 0)
vat_percentage                           (% of principal, e.g. 0.18 = 0.18%)
required_group_witnesses                 (default 2)
status                                   (active/inactive boolean)
```

---

## 4. Phase 1: Application Creation

### Endpoint

```
POST /api/v1/onboarding/loan-applications
POST /api/v1/loan-applications
```

### Request Body

```json
{
  "member_id": 1,
  "loan_product_id": 1,
  "application_type": "main | refinance | top_up",
  "requested_amount": 500000,
  "duration_months": 6,
  "existing_loan_balance": 0,
  "refinancing_amount": 0,
  "increment_amount": 0,
  "loan_purpose": "Expand shop inventory",
  "business_summary": "General dealer in Sinza...",
  "assessment": {
    "core_business_income": 300000,
    "other_income": 50000,
    "business_expenses": 150000,
    "household_expenses": 80000,
    "existing_external_debt": 0,
    "assessment_comment": "Stable income"
  },
  "utilizations": [
    { "purpose": "Shop inventory", "allocation_amount": 400000, "current_asset_value": 200000 },
    { "purpose": "Equipment", "allocation_amount": 100000, "current_asset_value": 50000 }
  ],
  "guarantors": [
    {
      "guarantor_type": "family | non_family",
      "name": "John Doe",
      "relationship": "Brother",
      "phone": "0712345678",
      "national_id": "1234567890",
      "house_number": "123",
      "street": "Maktaba",
      "ward": "Sinza",
      "district": "Kisarawe",
      "region": "Pwani"
    }
  ],
  "witness_member_ids": [5, 8, 12]
}
```

### Validation Rules

- `member_id`: must exist and be active
- `loan_product_id`: must exist and be active (`status = true`)
- `application_type`: one of `main`, `refinance`, `top_up`
- `requested_amount`: must be within product `[minimum_amount, maximum_amount]`
- `duration_months`: must be within product `[minimum_duration_months, maximum_duration_months]`
- `utilizations`: `allocation_amount` values must sum to exactly `requested_amount`
- `guarantors`: max 2 items
- `witness_member_ids`: max 10, must be active members of applicant's group (not the applicant)

### Business Rules

1. Member must have an active group membership
2. For `main` type: member must NOT have an existing active loan (`pending_disbursement`, `active`, or `overdue`)
3. For `refinance` or `top_up` type: member MUST have an existing active loan
4. For `refinance`: `refinancing_amount > 0` required
5. For `top_up`: `increment_amount > 0` and `existing_loan_balance > 0` required
6. Member cannot have another open application (status not in `rejected`, `cancelled`, `disbursed`)

### Response

```json
{
  "success": true,
  "message": "Loan application created.",
  "data": {
    "id": 1,
    "application_number": "VATI-LAF-2026-000078",
    "status": "draft",
    "member": { "id": 1, "membership_number": "VATI-M-2026-000123", "full_name": "Jane Smith" },
    "product": { "id": 1, "name": "Standard Group Loan" },
    "group": { "id": 5, "group_name": "Umoja Group" },
    "requested_amount": 500000,
    "duration_months": 6,
    "assessment": { ... },
    "utilizations": [ ... ],
    "guarantors": [ ... ],
    "group_witnesses": [ ... ]
  }
}
```

---

## 5. Phase 2: Compliance Evidence Collection

During `draft` or `returned` status, the applicant and supporting evidence are captured.

### 5a. Applicant Consent & Signatures

```
PUT /api/v1/loan-applications/{id}/compliance/applicant
```

```json
{
  "applicant_signature_path": "path/to/signature.png",
  "applicant_thumbprint_path": "path/to/thumbprint.png"
}
```

**Effect:**
- Captures signature and thumbprint
- Records consent against the active `LoanTerm`
- Sets `consented_at = now()`
- Sets `cancellation_deadline = now() + 3 days`

### 5b. Add Guarantor with Evidence

```
POST /api/v1/loan-applications/{id}/compliance/guarantors
```

```json
{
  "guarantor_type": "family",
  "name": "John Doe",
  "relationship": "Brother",
  "phone": "0712345678",
  "national_id": "1234567890",
  "house_number": "123",
  "street": "Maktaba",
  "ward": "Sinza",
  "district": "Kisarawe",
  "region": "Pwani",
  "business_address": "Shop 5, Sinza Market",
  "signature_path": "path/to/signature.png",
  "thumbprint_path": "path/to/thumbprint.png",
  "joint_photo_path": "path/to/joint_photo.png"
}
```

**Effect:**
- Creates `LoanGuarantor` record
- Auto-generates declaration text: *"I accept responsibility for repayment of this loan if the applicant defaults."*
- Sets `declaration_accepted_at = now()`

### 5c. Replace Nominees

```
PUT /api/v1/loan-applications/{id}/compliance/nominees
```

```json
{
  "nominees": [
    { "name": "Mary Smith", "relationship": "Wife", "percentage": 60 },
    { "name": "Tom Smith", "relationship": "Son", "percentage": 40 }
  ]
}
```

**Rule:** Nominee percentages must total exactly **100%**.

### 5d. Upload Documents

```
POST /api/v1/loan-applications/{id}/compliance/documents
```

Document types: `member_identity`, `guarantor_identity`, `local_government_letter`, `business_license`, `house_lease`, `member_signature`, `other`

### 5e. Verify Document

```
POST /api/v1/loan-applications/{id}/compliance/documents/{docId}/verify
```

```json
{
  "verification_status": "verified | rejected",
  "verification_remarks": "Clear copy"
}
```

---

## 6. Phase 3: Submission

### Endpoint

```
POST /api/v1/loan-applications/{id}/submit
```

### Transition

```
draft    ──> submitted
returned ──> submitted   (re-submission after return; increments credit_review_attempt)
```

### Pre-condition

- Application status must be `draft` or `returned`
- Nominee allocations must total exactly 100%

### Response

```json
{
  "success": true,
  "message": "Application submitted for review.",
  "data": {
    "id": 1,
    "status": "submitted",
    "submitted_at": "2026-08-18T10:30:00Z"
  }
}
```

---

## 7. Phase 4: Credit Review

### 7a. Assign Credit Officer

```
POST /api/v1/loan-applications/{id}/assign-credit-officer
```

```json
{
  "assigned_credit_officer_id": 15
}
```

**Rules:**
- Application must be `submitted` or `credit_review`
- Officer must have `credit_officer` role, be active, and belong to the same branch
- Transitions status to `credit_review`

### 7b. Submit Credit Review

```
POST /api/v1/loan-applications/{id}/credit-review
```

```json
{
  "decision": "recommend | return",
  "recommended_amount": 400000,
  "recommended_duration_months": 6,
  "overall_risk": "low",
  "remarks": "Applicant verified. Recommend reduction to 400K.",
  "member_verified": true,
  "group_membership_verified": true,
  "documents_verified": true
}
```

**Rules for `recommend`:**
- `recommended_amount` must be <= `requested_amount`
- `recommended_amount` must be >= product `minimum_amount`
- `recommended_duration_months` must be within product limits
- All three verification flags (`member_verified`, `group_membership_verified`, `documents_verified`) must be `true`
- Transitions to `recommended` status

**Rules for `return`:**
- Transitions to `returned` status (applicant can re-submit)

### 7c. Administrator Return (from recommended)

```
POST /api/v1/loan-applications/{id}/return
```

- Only works from `recommended` status
- Creates a `LoanApproval` record with `decision = 'returned'`
- Transitions to `returned` status

---

## 8. Phase 5: Approval

### Endpoints

```
POST /api/v1/loan-applications/{id}/approve
POST /api/v1/loan-applications/{id}/reject
```

### Approve

```json
{
  "remarks": "Approved for disbursement"
}
```

**Transition:** Any of `submitted`, `lo_review`, `abm_review`, `bm_review`, `credit_review`, `recommended` → `approved`

**What happens on approval:**
1. A `Loan` record is created with:
   - `principal_amount` = `recommended_amount` (falls back to `requested_amount`)
   - `number_of_installments` = `recommended_duration_months` (falls back to `duration_months`)
   - All financial figures calculated via `LoanCalculatorService::calculate()`
   - Weekly frequency: installments = `round(duration_months * 52 / 12)`
   - Monthly frequency: installments = `duration_months`
   - `installment_amount` = `total_repayment / number_of_installments`
   - `interest_amount` = `0` (interest-free lending)
   - `principal_balance` = `total_repayment` (the full factor-scheduled amount is the outstanding debt)
   - `interest_balance` = `0`
   - `total_balance` = `total_repayment`
   - Status: `pending_disbursement`
2. A `LoanApproval` audit record is created

### Reject

```json
{
  "remarks": "Insufficient income documentation"
}
```

**Transition:** Same eligible states → `rejected` (terminal)

---

## 9. Phase 6: Cooling-Off Period

After applicant consent is captured, a **3-day cooling-off window** is set (`cancellation_deadline = consented_at + 3 days`).

### Cancel Application

```
POST /api/v1/loan-applications/{id}/cancel
```

```json
{
  "reason": "Changed my mind about the loan"
}
```

**Rules:**
- Must be within the 3-day window (`now() < cancellation_deadline`)
- Application must not already be cancelled
- Loan must not have been disbursed yet
- Creates a `LoanCancellation` record
- Transitions to `cancelled` status (terminal)

---

## 10. Phase 7: Disbursement

### Endpoint

```
POST /api/v1/loans/{id}/disburse
```

### Request

```json
{
  "method": "cash | mpesa | airtel_money | mixx | halopesa | bank_transfer",
  "recipient_number": "0712345678",
  "bank_account": "0123456789",
  "reference_number": "MPESA-REF-12345",
  "provider_reference": "EXT-REF-67890",
  "disbursed_at": "2026-08-18",
  "first_payment_date": "2026-08-25"
}
```

### Pre-conditions

1. Loan status must be `pending_disbursement`
2. Application status must be `approved`
3. Cooling-off period must have expired (`now() >= cancellation_deadline`)
4. No cancellation record must exist

### What Happens

1. `LoanDisbursement` record created with amount, method, references
2. Loan status → `active`
3. `disbursement_date` set
4. `first_payment_date` set:
   - Weekly: `disbursement_date + 1 week` (default)
   - Monthly: `disbursement_date + 1 month` (default)
5. `maturity_date` set:
   - `first_payment_date + (installments - 1) weeks/months`
6. Application status → `disbursed`
7. Repayment schedule generated (installment records created)

### Response

```json
{
  "success": true,
  "message": "Loan disbursed successfully.",
  "data": {
    "id": 1,
    "amount": 500000,
    "method": "cash",
    "disbursed_at": "2026-08-18T14:00:00Z"
  },
  "loan": {
    "id": 1,
    "loan_number": "VATI-LN-2026-000012",
    "status": "active",
    "first_payment_date": "2026-08-25",
    "maturity_date": "2026-11-24"
  }
}
```

---

## 11. Phase 8: Repayment

### 11a. Post a Payment

```
POST /api/v1/loans/{id}/payments
```

```json
{
  "amount": 50000,
  "payment_method": "cash",
  "loan_installment_id": 1,
  "idempotency_key": "unique-key-from-device",
  "uuid": "client-uuid-v4",
  "reference_number": "MPESA-12345",
  "external_reference": "EXT-67890",
  "paid_at": "2026-08-25T10:00:00Z",
  "device_id": "DEVICE-001",
  "client_created_at": "2026-08-25T09:58:00Z",
  "remarks": "Cash collection at meeting"
}
```

**Key Rules:**
- Loan must be `active` or `overdue`
- Amount must be > 0
- Amount cannot exceed outstanding `total_balance` (silently capped if less)
- `idempotency_key` prevents duplicate payments (returns existing payment if key already used)

### Payment Allocation Waterfall

Payments are allocated across installments in order (installment 1, 2, 3...):

```
For each installment:
  1. Pay INTEREST first:  interest = min(remaining, interest_due - interest_paid - interest_exemption)
  2. Pay PRINCIPAL next:  principal = min(remaining, principal_due - principal_paid)
  3. Create PaymentAllocation record
  4. Update installment:  principal_paid, interest_paid, total_paid
  5. If total_paid + 0.009 >= (total_due - interest_exemption) → status = "paid"
     Else → status = "partially_paid"
  6. Continue with remaining amount to next installment
```

**Residual allocation:** If money remains after all installments, it is allocated directly against `loan.interest_balance` then `loan.principal_balance` with `loan_installment_id = null`.

**Loan balance update:**
```
loan.principal_balance -= total_principal_paid
loan.interest_balance  -= total_interest_paid
loan.total_balance      = principal_balance + interest_balance
if loan.total_balance <= 0.009 → loan.status = "settled"
```

### Response

```json
{
  "success": true,
  "message": "Payment posted successfully.",
  "data": {
    "id": 1,
    "payment_number": "VATI-PAY-2026-000345",
    "amount": 50000,
    "payment_method": "cash",
    "paid_at": "2026-08-25T10:00:00Z",
    "status": "posted",
    "allocations": [
      {
        "id": 1,
        "loan_installment_id": 1,
        "principal_amount": 35000,
        "interest_amount": 15000,
        "penalty_amount": 0
      }
    ]
  },
  "loan": {
    "id": 1,
    "loan_number": "VATI-LN-2026-000012",
    "status": "active",
    "principal_balance": 315000,
    "interest_balance": 135000,
    "total_balance": 450000
  }
}
```

### 11b. Reverse a Payment

```
POST /api/v1/payments/{id}/reverse
```

```json
{
  "reason": "Incorrect amount recorded by collector"
}
```

**Rules:**
- Only `posted` payments can be reversed
- Reversal is **irreversible** (no re-post)
- All installment paid amounts are decremented
- Loan balances are restored
- Loan status recalculated: if any installment is overdue → `overdue`, else → `active`

---

## 12. Phase 9: Settlement & Clearance

### 12a. Full Settlement

```
POST /api/v1/loans/{id}/settle
```

```json
{
  "settlement_date": "2026-11-24",
  "interest_waived": 20000,
  "security_offset": 50000,
  "cash_payment": 380000,
  "security_refund": 0
}
```

**Settlement Equation:**
```
interest_waived + security_offset + cash_payment = total_balance
```

All inputs must exactly clear the outstanding balance. This is validated:
```
final = total_balance - interest_waived - security_offset - cash_payment
if abs(final) > 0.009 → ERROR: "Settlement inputs must clear the full outstanding balance."
```

**Effect:**
- All loan balances zeroed
- Loan status → `settled`
- `LoanSettlement` record created
- `LoanClearance` record created with `status = 'pending'`

### 12b. Authorize Clearance

```
POST /api/v1/loans/{id}/clearance
```

```json
{
  "comments": "Final inspection complete. No outstanding issues.",
  "manager_signature_path": "path/to/signature.png"
}
```

**Rules:**
- Requires role: `super_admin`, `head_office_admin`, or `branch_manager`
- Loan must be `settled` with zero balance
- Clearance status: `pending` → `authorized`

---

## 13. Phase 10: Administration

### 13a. Default Notice

```
POST /api/v1/loans/{id}/default-notices
```

```json
{
  "delivery_method": "hand_delivery | registered_mail",
  "delivery_reference": "REC-12345",
  "notice_text": "You have 14 days to clear your outstanding balance..."
}
```

**Rules:**
- Loan must be `active` or `overdue` with `total_balance > 0`
- Creates a 14-day notice (`expires_at = issued_at + 14 days`)
- No automatic status transition on expiry

### 13b. Passbook Replacement

```
POST /api/v1/members/{id}/passbook-replacements
```

**Fee:** TZS 1,000

---

## 14. Loan Calculator

```
POST /api/v1/loan-calculator
```

### Request

```json
{
  "loan_product_id": 1,
  "principal": 500000,
  "duration_months": 6
}
```

### Formulas

```
// Fixed weekly payment factors (weekly products only)
weekly_payment  = principal × factor
  6 months  → factor 0.0445 (26 weekly installments)
  8 months  → factor 0.0360 (35 weekly installments)
 12 months  → factor 0.0295 (52 weekly installments)

// Interest-free lending: no interest is charged on any loan.
interest       = 0

processing_fee = principal × (processing_fee_percentage / 100)
insurance_fee  = principal × (insurance_percentage / 100)
vat            = principal × (vat_percentage / 100)          // flat % of principal (0.18%)
security_amount = principal × (security_percentage / 100)

total_charges     = processing_fee + insurance_fee + vat
amount_receivable = principal - security_amount
total_repayment   = weekly_payment × installment_count   // when a factor applies
                  = principal                            // otherwise
```

### Response

```json
{
  "principal": 500000,
  "interest": 0,
  "processing_fee": 5000,
  "insurance_fee": 7500,
  "vat": 900,
  "security_amount": 50000,
  "charges": 13400,
  "amount_receivable": 450000,
  "total_repayment": 500000
}
```

---

## 15. Repayment Schedule Generation

Generated automatically at disbursement by `RepaymentScheduleService::generate()`.

### Algorithm

```
count = number_of_installments

if interest_amount <= 0 (all new loans — interest-free):
  per_installment = floor(total_repayment / count, 2)

  for i = 1 to count:
    total_due     = per_installment   (last installment absorbs rounding remainder)
    principal_due = total_due         (every installment counts fully toward the balance)
    interest_due  = 0

else (legacy interest-bearing loans):
  principal_per = principal_amount / count
  interest_per  = interest_amount / count

  for i = 1 to count:
    principal_due = principal_per     (last installment absorbs rounding remainder)
    interest_due  = interest_per      (last installment absorbs rounding remainder)
    total_due     = principal_due + interest_due

  due_date:
    weekly  → first_payment_date + addWeeks(i - 1)
    monthly → first_payment_date + addMonths(i - 1)

  outstanding_balance = total_repayment - (sum of all previous total_due)
```

### Example (TZS 1M loan, 6 months / 26 weeks, factor 0.0445)

| # | Due Date | Principal | Interest | Total | Balance |
|---|---|---|---|---|---|
| 1 | Aug 25 | 44,500.00 | 0.00 | 44,500.00 | 1,112,500.00 |
| 2 | Sep 1 | 44,500.00 | 0.00 | 44,500.00 | 1,068,000.00 |
| ... | ... | ... | ... | ... | ... |
| 26 | Feb 16 | 44,500.00 | 0.00 | 44,500.00 | 0.00 |

Total repayment: TZS 1,157,000.

---

## 16. Payment Allocation Algorithm

### Visual Flow

```
Payment Amount (e.g. 120,000)
    │
    ▼
┌─────────────────────────────────────────────┐
│ Installment 1 (due: 90,000)                │
│   Interest due: 6,666.67 → pay 6,666.67   │
│   Principal due: 83,333.33 → pay 83,333.33│
│   Allocated: 90,000 │ Remaining: 30,000    │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│ Installment 2 (due: 90,000)                │
│   Interest due: 6,666.67 → pay 6,666.67   │
│   Principal due: 83,333.33 → pay 23,333.33│
│   Allocated: 30,000 │ Remaining: 0         │
│   Status: partially_paid                   │
└─────────────────────────────────────────────┘
```

### Installment Balance Formula

```
balance = max(0, total_due - total_paid - interest_exemption)
```

### Floating-Point Tolerance

All balance comparisons use `0.009` as the epsilon threshold:
```
if total_paid + 0.009 >= (total_due - interest_exemption) → PAID
```

---

## 17. Security (Collateral) Accounts

Members have a security account for collateral deposits and loan offsets.

### Models

- `MemberSecurityAccount` — per-member balance tracker
- `SecurityTransaction` — deposit/withdrawal records (user-level)
- `LoanSecurityTransaction` — loan-level security transactions

### Transaction Types

| Type | Effect |
|---|---|
| `deposit` | Increases balance |
| `adjustment` | Increases balance |
| `withdrawal` | Decreases balance |
| `loan_offset` | Decreases balance (used in settlement) |
| `refund` | Decreases balance |

### API

```
GET  /api/v1/members/{id}/security                    → Show account + transactions
POST /api/v1/members/{id}/security-transactions       → Create transaction
```

```json
{
  "type": "deposit | withdrawal | loan_offset | refund | adjustment",
  "amount": 50000,
  "loan_id": 1,
  "remarks": "Collateral deposit"
}
```

**Rule:** Balance cannot go below zero. `Insufficient security balance.` error thrown if debit exceeds balance.

---

## 18. Portfolio Analytics

### Summary

```
GET /api/v1/portfolio/summary?from=2026-08-01&to=2026-08-31
```

```json
{
  "expected_collection": 2500000,
  "collected": 2100000,
  "collection_rate": 84.0,
  "gross_loan_portfolio": 15000000,
  "at_risk_amount": 1200000,
  "portfolio_at_risk_par30": 8.0,
  "overdue_amount": 900000,
  "total_active_loans": 45,
  "total_active_members": 120
}
```

### Key Metrics

- **Expected collection**: Sum of `total_due` from installments due in the date range (active/overdue loans)
- **Collected**: Sum of `amount` from posted payments in the date range
- **Collection rate**: `collected / expected × 100`
- **Gross loan portfolio**: Sum of `total_balance` for all active/overdue loans
- **At-risk amount**: Sum of `principal_balance` from loans with overdue/partially-paid installments (due within last 30 days)
- **PAR 30**: `at_risk / portfolio × 100`

---

## 19. Complete API Reference

### Loan Applications

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `onboarding/loan-applications` | Create application (onboarding flow) |
| `GET` | `loan-applications` | List (filterable by branch, status, member_id) |
| `POST` | `loan-applications` | Create application |
| `GET` | `loan-applications/{id}` | Full detail |
| `PUT` | `loan-applications/{id}` | Update draft |
| `DELETE` | `loan-applications/{id}` | Delete draft (add `force=1` to permanently delete) |
| `POST` | `loan-applications/{id}/submit` | Submit for review |
| `POST` | `loan-applications/{id}/approve` | Approve |
| `POST` | `loan-applications/{id}/reject` | Reject (requires remarks) |
| `POST` | `loan-applications/{id}/return` | Return from recommended |
| `POST` | `loan-applications/{id}/cancel` | Cancel (cooling-off) |
| `POST` | `loan-applications/{id}/assign-credit-officer` | Assign officer |
| `POST` | `loan-applications/{id}/credit-review` | Submit review |
| `PUT` | `loan-applications/{id}/compliance/applicant` | Capture consent/signatures |
| `POST` | `loan-applications/{id}/compliance/guarantors` | Add guarantor |
| `PUT` | `loan-applications/{id}/compliance/nominees` | Replace nominees |
| `POST` | `loan-applications/{id}/compliance/documents` | Upload document |
| `POST` | `loan-applications/{id}/compliance/documents/{docId}/verify` | Verify document |
| `GET` | `loan-applications/{id}/documents` | List documents |
| `POST` | `loan-applications/{id}/documents` | Upload document (alternate) |
| `GET` | `loan-applications/{id}/documents/{docId}/download` | Download document |
| `GET` | `loan-applications/{id}/export` | PDF export |
| `GET` | `loan-applications/{id}/group-witnesses` | List witnesses |
| `POST` | `loan-applications/{id}/group-witnesses` | Confirm witness |

### Loans

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `loans` | List (filterable by branch, status, member_id) |
| `GET` | `loans/{id}` | Full detail with installments, payments, etc. |
| `GET` | `loans/{id}/schedule` | Repayment schedule + summary |
| `POST` | `loans/{id}/disburse` | Disburse loan |
| `POST` | `loans/{id}/payments` | Post payment |
| `POST` | `loans/{id}/settle` | Full settlement |
| `POST` | `loans/{id}/default-notices` | Issue default notice |
| `POST` | `loans/{id}/clearance` | Authorize clearance |
| `GET` | `loans/{id}/export` | PDF export |

### Payments

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `payments/{id}/reverse` | Reverse payment |

### Loan Products

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `loan-products` | List |
| `GET` | `loan-products/{id}` | Show |
| `POST` | `loan-products` | Create |
| `PUT` | `loan-products/{id}` | Update |
| `DELETE` | `loan-products/{id}` | Delete |

### Users, Attachments & Audit Trail

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `roles` | List roles with permissions |
| `GET` | `users` | List users (filter by `branch_id`) |
| `POST` | `users` | Create user (audited) |
| `GET` | `users/{id}` | Full detail incl. `attachments` + `audit_trail` |
| `PUT` | `users/{id}` | Update user (audited) |
| `DELETE` | `users/{id}` | Delete user (audited) |
| `GET` | `users/{id}/attachments` | List attachments |
| `POST` | `users/{id}/attachments` | Upload attachment (PDF/JPG/JPEG/PNG/DOC/DOCX, max 5 MB) |
| `GET` | `users/{id}/attachments/{attId}/download` | Download attachment |
| `DELETE` | `users/{id}/attachments/{attId}` | Delete attachment (audited; `force=1` permanently deletes) |
| `GET` | `system/audit-logs` | Global audit log (filter by `user_id`, date range, `log_name`, search) |
| `GET` | `system/audit-logs/{id}` | Single audit entry |

**User details audit trail:** `GET users/{id}` returns `audit_trail` — the latest 100 activities where the user is the actor (`direction: "performed"`) or the subject (`direction: "on_account"`). Each entry: `description`, `log_name`, `subject_type`, `subject_id`, `properties`, `performed_by`, `created_at`.

**Force delete:** soft-deletable records (members, loan applications, member documents, user attachments) accept a permanent-delete flag — web forms send `_force=1` (chosen via the SweetAlert "Delete forever" button), APIs pass `?force=1`. Force deletes bypass the trash, remove stored files where applicable, and are logged with `forced: true`.

### Supporting

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `loan-calculator` | Calculate loan figures |
| `GET` | `portfolio/summary` | Portfolio analytics |
| `GET` | `portfolio/branches` | Per-branch analytics |
| `GET` | `members/{id}/passbook` | View passbook |
| `POST` | `members/{id}/passbook-replacements` | Replace passbook |
| `GET` | `members/{id}/security` | View security account |
| `POST` | `members/{id}/security-transactions` | Create security transaction |

---

## 20. Database Schema

### Tables & Key Columns

#### `loan_products`
```
id, name, code (unique)
minimum_amount, maximum_amount (decimal 18,2)
minimum_duration_months, maximum_duration_months (unsigned int)
annual_interest_rate (decimal 8,4)
interest_method (default "reducing_balance")
repayment_frequency (default "weekly")
security_percentage, processing_fee_percentage, insurance_percentage, transaction_fee_percentage (decimal 8,4; transaction fee is legacy/unused)
membership_fee (decimal 18,2, legacy/unused)
vat_percentage (decimal 8,4, % of principal, e.g. 0.18)
required_group_witnesses (unsigned int, default 2)
status (boolean, default true)
```

#### `loan_applications`
```
id, application_number (unique)
member_id (FK), loan_product_id (FK), group_id (FK), branch_id (FK), created_by (FK)
application_type (default "main")
requested_amount, recommended_amount (decimal 18,2)
duration_months, recommended_duration_months (unsigned int)
existing_loan_balance, refinancing_amount, increment_amount (decimal 18,2, default 0)
loan_purpose (text), business_summary (text)
status (default "draft")
submitted_at (timestamp)
loan_term_id (FK), consent_declaration (text), consented_at, consented_ip
cancellation_deadline (timestamp)
applicant_signature_path, applicant_thumbprint_path
assigned_credit_officer_id (FK)
risk_level, credit_review_attempt (default 1)
```

#### `loan_utilizations`
```
id, loan_application_id (FK cascade)
purpose, allocation_amount (decimal 18,2), current_asset_value (decimal 18,2)
```

#### `loan_assessments`
```
id, loan_application_id (unique FK cascade)
core_business_income, other_income, business_expenses, household_expenses (decimal)
monthly_profit, disposable_income (computed)
existing_external_debt (decimal)
debt_service_ratio, affordability_score (decimal 8,4)
assessment_comment (text)
```

#### `loan_guarantors`
```
id, loan_application_id (FK cascade)
guarantor_type, name, relationship, phone
national_id, voter_id, house_number, street, ward, district, region
business_address, signature_path, thumbprint_path, joint_photo_path
declaration_text, declaration_accepted_at
```

#### `loan_documents`
```
id, loan_application_id (FK cascade)
document_type, file_path, original_name, mime_type, size_bytes
verification_status (default "pending")
is_required (boolean, default true)
uploaded_by (FK), verified_by (FK), verified_at, verification_remarks
remarks
```

#### `loan_group_witnesses`
```
id, loan_application_id (FK cascade)
group_id (FK), member_id (FK)
signature_path, confirmed_at, recorded_by (FK)
UNIQUE (loan_application_id, member_id)
```

#### `loan_approvals`
```
id, loan_application_id (FK cascade)
user_id (FK), role, decision, from_status, to_status
remarks, acted_at (timestamp)
```

#### `credit_reviews`
```
id, loan_application_id (FK cascade)
attempt (default 1)
decision, recommended_amount, recommended_duration_months
overall_risk, remarks
member_verified (bool), group_membership_verified (bool), documents_verified (bool)
reviewed_by (FK), reviewed_at (timestamp)
UNIQUE (loan_application_id, attempt)
```

#### `loan_terms`
```
id, version (unique)
title, body (longText)
effective_from, effective_until (date)
is_active (boolean), created_by (FK)
```

#### `loan_cancellations`
```
id, loan_application_id (unique FK)
reason (text), cancelled_at (timestamp), cancelled_by (FK)
```

#### `loans`
```
id, loan_number (unique)
loan_application_id (unique FK), member_id (FK), group_id (FK)
loan_product_id (FK), branch_id (FK)
business_name, loan_cycle (default "main")
principal_amount, adjusted_principal_amount, interest_amount (decimal 18,2)
interest_rate (decimal 8,4)
total_repayment, principal_balance, interest_balance, total_balance (decimal 18,2)
number_of_installments (unsigned int)
installment_amount, weekly_installment (decimal 18,2)
admission_fee, processing_fee, transaction_charges, other_charges, total_fees_and_vat (decimal 18,2)
refinancing_amount, increment_amount (decimal 18,2)
disbursement_date, first_payment_date, maturity_date (date)
status (default "pending_disbursement")
```

#### `loan_disbursements`
```
id, loan_id (unique FK)
amount (decimal 18,2)
method, recipient_number, bank_account, reference_number, provider_reference
disbursed_at (timestamp)
processed_by (FK), approved_by (FK)
status (default "pending")
```

#### `loan_installments`
```
id, loan_id (FK cascade)
installment_number (unsigned int, unique per loan)
due_date (date, indexed)
principal_due, interest_due, total_due (decimal 18,2, default 0)
principal_paid, interest_paid, total_paid (decimal 18,2, default 0)
interest_exemption (decimal 18,2, default 0)
outstanding_balance (decimal 18,2, default 0)
status (default "upcoming")
```

#### `payments`
```
id, uuid (unique), idempotency_key (unique)
payment_number (unique, VATI-PAY-YYYY-NNNNNN)
member_id (FK), loan_id (FK), branch_id (FK)
amount (decimal 18,2)
payment_method (cash/mpesa/airtel_money/mixx/halopesa/bank_transfer)
reference_number, external_reference
paid_at (timestamp)
collected_by (FK), device_id
client_created_at, server_received_at (timestamp)
sync_status (default "synced")
remarks (text)
status (default "posted")
reversed_by (FK), reversed_at (timestamp), reversal_reason (text)
```

#### `payment_allocations`
```
id, payment_id (FK cascade)
loan_installment_id (FK, nullable, nullOnDelete)
principal_amount, interest_amount, penalty_amount (decimal 18,2, default 0)
```

> **Note:** `penalty_amount` exists in schema but is not yet used by any service.

#### `loan_settlements`
```
id, settlement_number (unique, VATI-STL-YYYY-NNNNNN)
loan_id (unique FK), settlement_date (date)
principal_outstanding, interest_outstanding, interest_waived (decimal 18,2)
security_offset, cash_payment, security_refund, final_balance (decimal 18,2)
approved_by (FK), approved_at (timestamp)
```

#### `loan_clearances`
```
id, loan_id (unique FK)
loan_outstanding_amount, security_offset, cash_collection, security_refund (decimal 18,2)
comments (text), status (default "pending")
authorized_by (FK), authorized_at (timestamp)
manager_signature_path (text)
```

#### `loan_default_notices`
```
id, loan_id (FK)
notice_days (default 14)
issued_at, expires_at (timestamp)
delivery_method, delivery_reference
notice_text (text)
acknowledged_at (timestamp), issued_by (FK)
```

#### `loan_cycles`
```
id, loan_id (FK cascade)
business_name, cycle_type (default "main")
is_main_cycle (bool, default true), is_refinancing_cycle (bool, default false)
principal_amount, adjusted_principal_amount (decimal 18,2)
interest_rate (decimal 8,4)
disbursement_date, first_payment_date (date)
admission_fee, processing_fee, transaction_charges, increment_amount, refinancing_amount, other_charges (decimal 18,2)
vat_amount, total_fees_and_vat, total_with_interest (decimal 18,2)
weekly_installment (decimal 18,2), total_installments (int)
status (default "active"), notes (text)
```

#### `loan_installment_records` (passbook-style)
```
id, loan_id (FK cascade), loan_cycle_id (FK cascade)
installment_number, payment_date (date)
principal_amount, interest_amount, total_amount (decimal 18,2)
interest_exemption, outstanding_balance (decimal 18,2)
is_paid (bool, default false)
actual_payment_date (date)
collector_id (FK)
remarks, collector_notes, collector_signature, branch_manager_signature
```

#### `loan_security_transactions`
```
id, loan_id (FK cascade)
transaction_date (date)
security_amount, withdrawal_amount, balance (decimal 18,2)
collected_by (FK), approved_by (FK)
```

#### `member_security_accounts`
```
id, member_id (unique FK cascade)
balance (decimal 18,2, default 0)
```

#### `security_transactions`
```
id, transaction_number (unique)
member_security_account_id (FK), loan_id (nullable FK)
transaction_type, amount (decimal 18,2)
balance_before, balance_after (decimal 18,2)
remarks (text), created_by (FK), transaction_date (timestamp)
```

#### `loan_refinancings`
```
id, old_loan_id (FK), new_loan_id (FK)
old_outstanding_balance, new_principal_amount, net_disbursement_amount (decimal 18,2)
processed_by (FK), processed_at (timestamp)
```

#### `user_attachments`
```
id, user_id (FK cascade)
title (nullable), file_name, file_path
mime_type (nullable), file_size (bigint nullable)
description (text nullable), uploaded_by (nullable FK)
timestamps + soft deletes
index: (user_id, created_at)
```

---

## 21a. Audit Trail

Every significant action is written to `activity_log` (spatie/laravel-activitylog) with the acting user as causer. Viewable via `GET system/audit-logs` and per-user in `GET users/{id} → audit_trail`.

| Log name | Audited events |
|---|---|
| `auth` | User logged in, Failed login attempt, User logged out, Password reset link requested, Password reset completed, Password changed |
| `default` | All loan lifecycle events (application submit/approve/reject, credit review, disbursement, payments, settlement, clearance), onboarding, KYC/photo/document changes, group visits, data purge, member & application deletions (with `forced: true` when permanent) |
| `groups` | Group created / updated / deleted |
| `users` | User account created / updated / deleted, attachment uploaded / deleted, role permissions updated |

Master-data CRUD is audited too: branches, regions, areas, loan products and system settings log create/update/delete with the changed fields.

Notes:
- Passwords are never stored in audit properties — only `password_changed: true/false`.
- Failed login attempts are recorded without a causer (email + IP only).

---

## 21. Notifications

The system sends notifications at these lifecycle points:

| Event | Recipients |
|---|---|
| `loan_application_submitted` | Application originators |
| `loan_application_approved` | Application originators |
| `loan_application_rejected` | Application originators |
| `application_assigned` | Assigned credit officer |
| `application_returned` | Application originators |
| `credit_recommendation` | Branch managers, credit officers, head office |
| `loan_disbursed` | Application originators |
| `payment_posted` | Application originators |
| `payment_reversed` | Application originators |
| `default_notice_issued` | Application originators |

---

## 22. Business Constants

| Constant | Value | Context |
|---|---|---|
| Cooling-off period | 3 days | After applicant consent captured |
| Default notice period | 14 days | Formal cure period for overdue loans |
| Passbook replacement fee | TZS 1,000 | Flat fee |
| Max guarantors per application | 2 | Enforced at creation |
| Max witnesses per application | 10 | Enforced at creation |
| Nominee allocation total | 100% | Must exactly equal 100% |
| Floating-point tolerance | 0.009 | Used in all balance comparisons |
| Branch access (admin) | `super_admin` or `head_office_admin` | See all branches |
| Branch access (others) | Scoped to `branch_id` | See own branch only |
| Payment methods | `cash`, `mpesa`, `airtel_money`, `mixx`, `halopesa`, `bank_transfer` | Valid disbursement/payment methods |
| Document types | `member_identity`, `guarantor_identity`, `local_government_letter`, `business_license`, `house_lease`, `member_signature`, `other` | Loan document categories |

---

## Status Transition Diagram

```
APPLICATION LIFECYCLE:

  ┌──────────────────────────────────────────────────────────────────────┐
  │                                                                      │
  │  ┌───────┐    submit    ┌───────────┐    assign    ┌──────────────┐ │
  │  │ DRAFT │─────────────>│ SUBMITTED │────────────>│ CREDIT_REVIEW│ │
  │  └───┬───┘              └─────┬─────┘              └──┬───┬───┬──┘ │
  │      │                        │                       │   │   │     │
  │      │ return                 │ reject                │   │   │     │
  │      │ <──────────────────────┼──┐                    │   │   │     │
  │      │                        │  │                    │   │   │     │
  │      │                        │  │   recommend        │   │   │     │
  │      │                        │  │  ┌─────────────────┘   │   │     │
  │      │                        │  │  │                     │   │     │
  │      │                        │  │  ▼                     │   │     │
  │      │                        │  │ ┌──────────────┐      │   │     │
  │      │                        │  │ │ RECOMMENDED  │      │   │     │
  │      │                        │  │ └──────┬───────┘      │   │     │
  │      │                        │  │        │              │   │     │
  │      │                        │  │  approve│     return  │   │     │
  │      │                        │  │        │    ┌─────────┘   │     │
  │      │                        │  │        ▼    ▼             │     │
  │      │                        │  │ ┌──────────────┐          │     │
  │      │                        │  │ │   APPROVED   │←─────────┘     │
  │      │                        │  │ └──────┬───────┘                │
  │      │                        │  │        │ reject                  │
  │      │                        │  │        ▼                        │
  │      │                        │  │ ┌──────────────┐                │
  │      │                        │  │ │   REJECTED   │ (terminal)     │
  │      │                        │  │ └──────────────┘                │
  │      │ cancel                 │  │                                 │
  │      │ (within 3 days)        │  │                                 │
  │      ▼                        │  │                                 │
  │  ┌──────────┐                 │  │                                 │
  │  │CANCELLED │ (terminal)      │  │                                 │
  │  └──────────┘                 │  │                                 │
  │                               │  │                                 │
  └───────────────────────────────┘  │                                 │
                                     │                                 │
  ┌──────────────────────────────────┘                                 │
  │                                                                    │
  ▼  (after approval + cooling-off)                                    │
  │                                                                    │
  │  DISBURSE ──> DISBURSED                                            │
  │                                                                    │
  └────────────────────────────────────────────────────────────────────┘


LOAN LIFECYCLE:

  pending_disbursement ──[disburse]──> active ──[payment]──> settled
                           │               │
                           │               ▼
                           │           overdue ──[payment]──> settled
                           │
                           ▼
                      cancelled
```

---

## Key Data Relationships

```
LoanApplication
  ├── belongsTo: Member, LoanProduct, MemberGroup, Branch, User (created_by)
  ├── hasOne: LoanAssessment, LoanCancellation, Loan (created on approval)
  ├── hasMany: LoanUtilization[], LoanGuarantor[], LoanDocument[]
  │             LoanGroupWitness[], LoanApproval[], CreditReview[]
  └── belongsTo: LoanTerm, User (assigned_credit_officer)

Loan
  ├── belongsTo: LoanApplication, Member, MemberGroup, LoanProduct, Branch
  ├── hasOne: LoanDisbursement, LoanSettlement, LoanClearance
  ├── hasMany: LoanInstallment[], Payment[], LoanDefaultNotice[]
  │             LoanCycle[], LoanInstallmentRecord[], LoanSecurityTransaction[]
  └── computed: total_security_amount, current_security_balance

LoanInstallment
  ├── belongsTo: Loan
  └── hasMany: PaymentAllocation[]

Payment
  ├── belongsTo: Loan, Member
  └── hasMany: PaymentAllocation[]

PaymentAllocation
  ├── belongsTo: Payment
  └── belongsTo: LoanInstallment (nullable)
```
