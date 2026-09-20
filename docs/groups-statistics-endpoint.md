# Group Statistics Endpoint — Mobile Integration Guide

Per-group portfolio statistics for the Flutter loan-officer app.

- **Method / URL:** `GET /api/v1/groups/{group}/statistics`
- **Auth:** `Bearer` token (`auth:sanctum`). No branch-access or role/permission gate — any authenticated user may call it with a valid `group` id.
- **Route model binding:** `{group}` is the **numeric `MemberGroup` id** (e.g. `5`), bound via route model binding to the `MemberGroup` model.

---

## Response envelope

All JSON responses from the Laravel API use a consistent envelope. This endpoint:

```json
{
  "success": true,
  "data": { ... }
}
```

### `data` object — descriptive field reference

All monetary values are returned as **numbers (float, 2-decimal precision already applied where relevant)**. Percentages are 0–100.

| Field | Type | Description |
|-------|------|-------------|
| `group_id` | int | The group id (echoes the `{group}` path segment). |
| `total_members` | int | Total members ever added to the group. |
| `active_members` | int | Members currently with `status = active`. |
| `total_loans` | int | All loans under the group. |
| `active_loans` | int | Loans in `active` / `overdue` status. |
| `members_with_active_loans` | int | Distinct members holding an active/overdue loan. |
| `total_loan_applications` | int | All loan applications ever submitted for the group. |
| `outstanding_portfolio` | float | Sum of `total_balance` across active/overdue loans. |
| `total_disbursed` | float | Sum of `principal_amount` for disbursed (non-pending/rejected/cancelled) loans. |
| `expected_weekly_collection` | float | Sum of `total_due` for installments due this week. |
| `actual_weekly_collection` | float | Sum of **posted** payments received this week. |
| `monthly_collections` | float | Sum of **posted** payments received this month, up to today. |
| `outstanding_weekly_collection` | float | `max(0, expected - actual)` for the week, rounded to 2dp. |
| `collection_rate` | float | `actual / expected * 100` (0 if `expected = 0`), rounded to 2dp. |
| `arrears` | float | Sum of past-due installments not fully paid/waived (capped at ≥ 0 each). |
| `par_1` | float | Portfolio at risk — installments ≥ 1 day overdue. |
| `par_7` | float | Portfolio at risk — installments ≥ 7 days overdue. |
| `par_30` | float | Portfolio at risk — installments ≥ 30 days overdue. |

### PAR definition

`par_n` = `(sum of principal_balance of active/overdue loans having any installment due_date ≤ today−n days and not paid/waived) / outstanding_portfolio × 100`. Returns `0` when the outstanding portfolio is `0`.

---

## Example request

```
GET /api/v1/groups/5/statistics
Authorization: Bearer <sanctum-token>
```

## Example response

```json
{
  "success": true,
  "data": {
    "group_id": 5,
    "total_members": 25,
    "active_members": 23,
    "total_loans": 18,
    "active_loans": 16,
    "members_with_active_loans": 21,
    "total_loan_applications": 27,
    "outstanding_portfolio": 5420000.0,
    "total_disbursed": 8750000.0,
    "expected_weekly_collection": 385000.0,
    "actual_weekly_collection": 368500.0,
    "monthly_collections": 1210500.0,
    "outstanding_weekly_collection": 16500.0,
    "collection_rate": 95.71,
    "arrears": 48250.5,
    "par_1": 3.42,
    "par_7": 2.1,
    "par_30": 0.8
  }
}
```

> Monetary example values above use `.0` float formatting for clarity; in practice values are plain JSON numbers with 2-decimal rounding. Percentages (`collection_rate`, `par_*`, `arrears`) are already rounded — display them directly without extra rounding.

---

## Validation / 404 handling

- **404** — invalid or non-existent `group` id. Laravel's API renderer returns `{ "success": false, "message": "..." }` with HTTP `404`.
- **401** — missing/invalid bearer token (middleware `auth:sanctum`).

---

## Field naming rules for the Flutter side

- Fields are `snake_case`, never `camelCase`.
- Distinguish carefully:
  - `outstanding_portfolio` = balance owed on loans (a **desktop/portfolio** metric).
  - `arrears` = past-due installment amounts (installment-level).
  - `par_1/7/30` = **percentages** (0–100), not currency.
- `collection_rate` is a **percentage**; `outstanding_weekly_collection` is **currency**.
- Booleans are used only in `status` comparisons server-side; this endpoint has no boolean fields.
