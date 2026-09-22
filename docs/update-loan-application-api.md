# Update Loan Application API — Request Body

Updates an existing loan application. Any application can be updated **except finalised ones**
(`approved`, `disbursed`, `rejected`, `cancelled`). The amounts are re-calculated against the
selected loan product, use-of-funds/assessment data is refreshed, and guarantors / group witnesses
are replaced when their keys are present.

- **Base path:** `/api/v1`
- **Auth:** `Bearer <sanctum-token>` in the `Authorization` header — invalid/missing token returns `401`.
- **Method:** `PUT` or `PATCH` (also accepted: `POST` — the route is registered for all three verbs).
- **URL:** `/api/v1/loan-applications/{loan_application}` where `{loan_application}` is the
  **numeric `loan_applications.id`** (returned as `data.id` on create / in list responses).
- **Content-Type:** `application/json`.
- **Response:** HTTP `200` → `{ "success": true, "data": <LoanApplicationResource> }`.

> **Partial vs full update:** the HTTP rules mark every top-level field `sometimes` (optional), but
> the service layer still reads `member_id`, `loan_product_id`, `application_type`, `requested_amount`
> and `duration_months` to recompute the figures — **always send all five core fields**.

---

## Request body table

| Field                                            | Type         | Required          | Rules                                                             | Notes |
|--------------------------------------------------|--------------|-------------------|-------------------------------------------------------------------|-------|
| `member_id`                                      | int          | yes (core set)    | `exists:members,id`                                                | Changing the applicant is **not** allowed → 422. |
| `loan_product_id`                                | int          | yes (core set)    | `exists:loan_products,id`                                          | Product must be active → else 422. |
| `application_type`                               | string       | yes (core set)    | `in: main, refinance, top_up`                                      | `main` requires no current loan; `refinance`/`top_up` require one. |
| `requested_amount`                               | number       | yes (core set)    | `numeric`, `min: product.minimum_amount`, `max: product.maximum_amount` | |
| `duration_months`                                | int          | yes (core set)    | `integer`, `min: product.minimum_duration_months`, `max: product.maximum_duration_months` | |
| `existing_loan_balance`                          | number\|null | no                | `numeric`, `min: 0`                                                | Must be `> 0` for `refinance` / `top_up`. |
| `refinancing_amount`                             | number\|null | no                | `numeric`, `min: 0`                                                | Must be `> 0` for `refinance`. |
| `increment_amount`                               | number\|null | no                | `numeric`, `min: 0`                                                | Must be `> 0` for `top_up`. |
| `loan_purpose`                                   | string\|null | no                | `string`, `max: 2000`                                              | |
| `business_summary`                               | string\|null | no                | `string`, `max: 5000`                                              | |
| `assessment`                                     | object\|null | no                | see below                                                          | Upserted; scores recomputed server-side. |
| `utilizations`                                   | array\|null  | no                | see below                                                          | Sum of allocations must equal `requested_amount`. |
| `guarantors`                                     | array\|null  | no                | array, `max: 2`; see below                                          | Sending this key **replaces** all guarantors. |
| `witness_member_ids`                             | array\|null  | no                | array, `max: 10`; each `integer, distinct, exists:members,id`      | Sending this key **replaces** all witnesses. |

### `assessment` object

| Field                | Type         | Rules             |
|----------------------|--------------|-------------------|
| `core_business_income` | number\|null | `numeric`, `min: 0` |
| `other_income`         | number\|null | `numeric`, `min: 0` |
| `business_expenses`    | number\|null | `numeric`, `min: 0` |
| `household_expenses`   | number\|null | `numeric`, `min: 0` |
| `existing_external_debt` | number\|null | `numeric`, `min: 0` |
| `assessment_comment`   | string\|null | `string`, `max: 5000` |

The service derives `monthly_profit`, `disposable_income`, `debt_service_ratio`, and
`affordability_score` and stores them on the assessment record.

### `utilizations[]` items

| Field                 | Type         | Rules                                   |
|-----------------------|--------------|-----------------------------------------|
| `purpose`             | string       | `required_with: utilizations`, `max: 255` |
| `allocation_amount`   | number       | `numeric`, `gt: 0`                        |
| `current_asset_value` | number\|null | `numeric`, `min: 0`                       |

Empty rows (`purpose` blank and `allocation_amount` = 0) are filtered out automatically. The total
`allocation_amount` of the remaining rows must equal `requested_amount` (tolerance 0.01).

### `guarantors[]` items (max 2)

| Field             | Type         | Rules                                                        |
|-------------------|--------------|--------------------------------------------------------------|
| `id`              | int\|null    | Existing guarantor id (omit / null for a new guarantor).     |
| `guarantor_type`  | string       | `in: family, non_family`                                     |
| `name`            | string       | `max: 150`                                                   |
| `relationship`    | string       | `max: 100`                                                   |
| `phone`           | string       | `max: 20`                                                    |
| `national_id`     | string\|null | `max: 50`                                                    |
| `voter_id`        | string\|null | `max: 50`                                                    |
| `house_number`    | string\|null | `max: 100`                                                   |
| `street`          | string\|null | `max: 100`                                                   |
| `ward`            | string\|null | `max: 100`                                                   |
| `district`        | string\|null | `max: 100`                                                   |
| `region`          | string\|null | `max: 100`                                                   |
| `business_address`| string\|null | `max: 1000`                                                  |

### `witness_member_ids[]`

Numeric `members.id`s of the applicant's group-mates. Every id must be a **different**, **active**
member of the **applicant's own group** (the applicant themselves is not allowed).

---

## Example request body

```json
{
  "member_id": 401,
  "loan_product_id": 3,
  "application_type": "main",
  "requested_amount": 5000000,
  "duration_months": 12,
  "loan_purpose": "Working capital for tailoring business",
  "business_summary": "Sells clothing in Mpakani market; 6 years experience.",
  "assessment": {
    "core_business_income": 1800000,
    "other_income": 0,
    "business_expenses": 600000,
    "household_expenses": 400000,
    "existing_external_debt": 0,
    "assessment_comment": "Consistent income, good margin."
  },
  "utilizations": [
    { "purpose": "Stock purchase", "allocation_amount": 3000000, "current_asset_value": null },
    { "purpose": "Equipment", "allocation_amount": 2000000 }
  ],
  "guarantors": [
    {
      "id": null,
      "guarantor_type": "family",
      "name": "Juma Musa",
      "relationship": "Brother",
      "phone": "255710000002",
      "national_id": "19851011-12345-67890"
    }
  ],
  "witness_member_ids": [405, 406]
}
```

---

## Behaviour on success

1. Figures are recomputed from the product: `calc_interest`, `calc_processing_fee`,
   `calc_insurance_fee`, `calc_vat`, `calc_security_amount`, `calc_charges`,
   `calc_amount_receivable`, `calc_total_repayment`.
2. `assessment` is upserted and the derived scores recalculated.
3. `utilizations` are **deleted and recreated**; `guarantors` / `witness_member_ids` are replaced
   only when the respective key is present.
4. Notice: the update **clears compliance evidence** — `loan_term_id`, `consent_declaration`,
   `consented_at`, `consented_ip`, `cancellation_deadline`, `applicant_signature_path`, and
   `applicant_thumbprint_path` are all reset to `null` so compliance must be re-captured after editing.
5. `group_id` and `branch_id` on the application are re-synced from the applicant's current group.

---

## Errors

| HTTP | Condition                                                                | Message (example) |
|------|--------------------------------------------------------------------------|-------------------|
| 401  | Missing/invalid bearer token.                                            | `Unauthenticated.` |
| 404  | `{loan_application}` does not exist.                                     | — |
| 422  | Application is finalised (`approved`, `disbursed`, `rejected`, `cancelled`). | `Finalised loan applications cannot be edited.` |
| 422  | `member_id` differs from the current applicant.                          | `The applicant cannot be changed on an existing draft. Create a new application instead.` |
| 422  | Applicant not active / no matching active group membership / group inactive. | `A draft application requires an active member with a matching active group membership.` |
| 422  | Applicant already has another open application.                          | `The member already has another open loan application.` |
| 422  | `main` **with** a current loan.                                          | `A member with a current loan must use the refinance or top-up application type.` |
| 422  | `refinance`/`top_up` **without** a current loan.                         | `Refinance and top-up applications require a current loan.` |
| 422  | Use-of-funds allocations ≠ `requested_amount`.                           | `Use-of-funds allocations must total the requested amount.` |
| 422  | Inactive product.                                                        | `The selected loan product is inactive.` / `The selected loan product is inactive.` |
| 422  | Witness not an active group member.                                      | `Every group witness must be another active member of the applicant’s group.` |