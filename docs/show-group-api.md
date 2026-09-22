# Show Group Details API — Members & Loan Applications

`GET /api/v1/groups/{group}` returns a single group together with (a) a **snapshot of its members**
and (b) **all loan applications linked to the group's members**. It is the main endpoint the Flutter /
mobile loan-officer app uses to render a group's detail screen.

- **Base path:** `/api/v1`
- **Auth:** `Bearer <sanctum-token>` in the `Authorization` header. The endpoint is behind the
  `auth:sanctum` middleware — a missing/invalid token returns HTTP `401`.
- **Route model binding:** `{group}` is the **numeric `member_groups.id`** (e.g. `12`).
- **Response:** HTTP `200` with `{ "success": true, "data": { ... } }`.

---

## Response shape

The `data` payload is serialized by `App\Http\Resources\GroupResource`:

| Field                      | Type                | Description                                                                                     |
|----------------------------|---------------------|-------------------------------------------------------------------------------------------------|
| `id`                       | int                 | Group id.                                                                                       |
| `group_code`               | string              | Generated code, e.g. `G-0001`.                                                                  |
| `group_name`               | string              | Group name.                                                                                     |
| `meeting_day`              | string\|null        | One of Monday–Sunday.                                                                           |
| `meeting_time`             | string\|null        | `HH:MM` (24h, first 5 chars of stored value).                                                   |
| `meeting_location`         | string\|null        |                                                                                                 |
| `region` / `district` / `ward` / `location` | string\|null   | Administrative / physical location.                                                             |
| `status`                   | bool                | `true` = active.                                                                                |
| `members_count`            | int                 | Total members in the group.                                                                     |
| `loans_count`              | int                 | Total loans whose `group_id` equals this group.                                                 |
| `loan_applications_count`  | int                 | Total applications whose `group_id` equals this group (see note below).                         |
| `branch`                   | object\|null        | `{ id, branch_code, branch_name, phone, email, address }`.                                      |
| `loan_officer`             | object\|null        | `{ id, name }`.                                                                                 |
| `members`                  | array               | Member snapshot, newest 20 first (see below).                                                   |
| `applications`             | array               | **All** loan applications of the group's members, newest first (see below).                     |
| `created_at` / `updated_at`| string (ISO 8601)   |                                                                                                 |

> **Important:** the `applications` array and `loan_applications_count` are counted differently.
> - `loan_applications_count` is a count of applications where `loan_applications.group_id = group`
>   (the model's `loanApplications` relation).
> - The `applications` array contains every application whose **member** belongs to the group,
>   resolved by the member's current `group_id`, regardless of `loan_applications.group_id`.
>
> These two numbers can differ for members who changed groups with applications still carrying an old
> `group_id`.

---

## `members` array

Loaded via `member_groups → members` (`Member.group_id`), ordered `created_at` newest first, capped
at the **most recent 20 members**. Use `GET /api/v1/groups/{group}/members` for the full paged list.

Each entry:

| Field                     | Type   | Description                                                                                               |
|---------------------------|--------|-----------------------------------------------------------------------------------------------------------|
| `id`                      | int    | Member id.                                                                                                |
| `membership_number`       | string |                                                                                                           |
| `first_name` / `last_name`| string |                                                                                                           |
| `phone`                   | string |                                                                                                           |
| `photo_url`               | string\|null | Absolute URL when `photo_path` exists, otherwise `null`.                                             |
| `status`                  | string | Member status (e.g. `active`).                                                                            |
| `current_loans_count`     | int    | Loans with status `pending_disbursement`, `active`, or `overdue`.                                         |
| `outstanding_loan_balance`| number | Sum of `total_balance` over those same loans (0 when none).                                               |

### Example

```json
{
  "id": 401,
  "membership_number": "M-00042",
  "first_name": "Asha",
  "last_name": "Musa",
  "phone": "255710000001",
  "photo_url": "https://api.example.com/storage/members/1.png",
  "status": "active",
  "current_loans_count": 1,
  "outstanding_loan_balance": "780000.00"
}
```

---

## `applications` array

Loaded with `LoanApplication::whereIn('member_id', <ids of the group's members>)`, eager-loading
`member` and `product`, ordered `created_at` newest first. **No limit / no pagination** — every
application of every member is returned.

Each entry is serialized by `App\Http\Resources\LoanApplicationResource`. Key fields:

| Field                       | Type            | Description                                                                   |
|-----------------------------|-----------------|-------------------------------------------------------------------------------|
| `id`                        | int             | Application id. Used as `{application}` in application sub-resources.         |
| `application_number`        | string          |                                                                               |
| `member`                    | object          | Full `MemberResource` (member details).                                       |
| `product`                   | object          | The `LoanProduct` (fields: id, name, code, interest rate, etc.).              |
| `application_type`          | string          |                                                                               |
| `requested_amount`          | string\|number  | Money value; **decimal:2** casts serialize as strings, e.g. `"1000.00"`.       |
| `amount_receivable`         | string\|null    | Net amount after security & charges, when a calculator breakdown exists.      |
| `recommended_amount`        | string\|null    | **decimal:2** — `null` before credit recommendation.                          |
| `duration_months`           | int             |                                                                               |
| `recommended_duration_months` | int\|null     |                                                                               |
| `risk_level`                | string\|null    |                                                                               |
| `status`                    | string          | Enum value: `draft`, `submitted`, `lo_review`, `abm_review`, `bm_review`, `credit_review`, `recommended`, `approved`, `rejected`, `returned`, `cancelled`, `disbursement_pending`, `disbursed`. |
| `calculator_breakdown`      | object\|null    | Stored calculation breakdown (`principal`, `interest`, `processing_fee`, `insurance_fee`, `vat`, `security_amount`, `charges`, `amount_receivable`, `total_repayment`). |
| `loan`                      | object\|null    | Linked loan after disbursement, when loaded.                                  |
| `consented_at` / `submitted_at` | string\|null | ISO 8601.                                                                   |
| `guarantors` / `documents` / `nominees` / `approvals` | array | Loaded when present.                                   |

> The full resource also includes assessment, utilizations, loan term, witnesses, cancellation,
> requirements and more. It is the same serialization used by
> `GET /api/v1/loan-applications/{application}`.

### Example (abridged)

```json
{
  "id": 1501,
  "application_number": "LA-00088",
  "member": {
    "id": 401,
    "membership_number": "M-00042",
    "first_name": "Asha",
    "last_name": "Musa"
  },
  "product": {
    "id": 3,
    "name": "Biashara",
    "code": "BIZ"
  },
  "application_type": "new_loan",
  "requested_amount": "1000000.00",
  "amount_receivable": "985000.00",
  "recommended_amount": null,
  "duration_months": 12,
  "risk_level": null,
  "status": "submitted",
  "calculator_breakdown": {
    "total_repayment": "1425000.00"
  },
  "created_at": "2026-09-22T08:30:00.000000Z"
}
```

---

## Errors

| HTTP | When                                          | Body                                  |
|------|-----------------------------------------------|---------------------------------------|
| 401  | Missing/invalid bearer token.                 | `{ "message": "Unauthenticated." }`   |
| 404  | `{group}` does not exist.                     | `{ "message": "No query results..." }`|

The endpoint is read-only — it never creates, updates, or deletes records.