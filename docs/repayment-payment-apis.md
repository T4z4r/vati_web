# Repayment Payment APIs — Edit Amount & Undo Payment

These endpoints let a repayment (a `Payment` record) be **edited** (amount changed) or **undone**
(reversed) after it has been posted to a loan. They are used to correct collection mistakes in the
Flutter / mobile loan-officer app and the web portal.

- **Base path:** `/api/v1`
- **Auth:** `Bearer <sanctum-token>` inside `Authorization` header. All endpoints live behind the
  `auth:sanctum` middleware — an invalid or missing token returns HTTP `401`.
- **Route model binding:** `{payment}` is the **numeric `payments.id`** (e.g. `42`), *not* the
  `payment_number` string. The numeric id is returned as `data.id` when posting a repayment via
  `POST /api/v1/loans/{loan}/payments`.

---

## 1. Edit repayment amount

Change the amount of an existing **posted** payment. The payment's allocations are recalculated
against the loan's repayment schedule (interest first, then principal, waterfall-style), and the
loan's outstanding balances are updated accordingly.

- **Method / URL:** `PUT` or `PATCH /api/v1/payments/{payment}`
- **Also accepted:** `POST /api/v1/payments/{payment}` (the API uses the same route for all three verbs)

### Request body

| Field    | Type   | Required | Validation                                      | Description                              |
|----------|--------|----------|-------------------------------------------------|------------------------------------------|
| `amount` | number | yes      | `numeric`, > 0, ≤ outstanding loan balance      | The new repayment amount, must differ from the current amount. |
| `reason` | string | no       | `string`                                        | Optional audit note stored in the activity log. |

### Example request

```
PATCH /api/v1/payments/42
Authorization: Bearer <sanctum-token>
Content-Type: application/json

{
  "amount": 150000,
  "reason": "Collector entered a wrong amount"
}
```

### Example response — HTTP 200

```json
{
  "success": true,
  "message": "Payment amount edited successfully.",
  "data": {
    "id": 42,
    "uuid": null,
    "idempotency_key": null,
    "payment_number": "VATI-PAY-2026-000007",
    "member_id": 3,
    "loan_id": 5,
    "branch_id": 1,
    "amount": "150000.00",
    "payment_method": "cash",
    "reference_number": null,
    "external_reference": null,
    "paid_at": "2026-09-22T10:00:00.000000Z",
    "collected_by": 1,
    "device_id": null,
    "client_created_at": null,
    "server_received_at": "2026-09-22T10:05:00.000000Z",
    "sync_status": "synced",
    "remarks": null,
    "status": "posted",
    "reversed_by": null,
    "reversed_at": null,
    "reversal_reason": null,
    "created_at": "2026-09-22T10:05:00.000000Z",
    "updated_at": "2026-09-22T10:06:00.000000Z",
    "allocations": [
      {
        "id": 77,
        "payment_id": 42,
        "loan_installment_id": 1,
        "principal_amount": "150000.00",
        "interest_amount": "0.00"
      }
    ]
  },
  "loan": {
    "id": 5,
    "loan_number": "VATI-L-2026-000003",
    "status": "active",
    "principal_balance": "850000.00",
    "interest_balance": "0.00",
    "total_balance": "850000.00"
  }
}
```

> `data.amount` and the monetary values are returned as strings (matching the decimal column
> format). `loan.total_balance` is the loan balance **after** the edit.

### Error responses

All errors reuse the standard envelope.

| HTTP | Scenario | Example response |
|------|----------|------------------|
| 401  | Missing/invalid bearer token | `{ "success": false, "message": "Unauthenticated." }` |
| 404  | Payment id does not exist | `{ "success": false, "message": "Resource not found." }` |
| 422  | Validation / business rule failed | see below |

```json
// Amount missing / zero / negative / non-numeric
{ "success": false, "message": "The amount must be greater than 0.", "errors": { "amount": ["The amount must be greater than 0."] } }

// Amount unchanged
{ "success": false, "message": "The new amount must be different from the current payment amount." }

// Amount exceeds the loan's outstanding balance
{ "success": false, "message": "The new payment amount cannot exceed the outstanding loan balance." }

// Payment already reversed
{ "success": false, "message": "Only posted payments can be edited." }
```

**Business rules:**

- Only payments with `status = posted` can be edited.
- The new amount must differ from the current amount and be `> 0`.
- The new amount cannot exceed the loan's outstanding (`total_balance`) balance.
- The loan must be `active`, `overdue`, or `settled`.
- The whole operation runs inside a database transaction — if any rule fails, nothing is changed.

---

## 2. Undo (reverse) a payment

Completely undo a **posted** payment. All allocations are removed from the loan's installments,
the principal/interest balances are restored, and the payment is marked `reversed`. A reversed
payment can **not** be edited or reversed again (re-post it instead).

- **Method / URL:** `POST /api/v1/payments/{payment}/delete`

### Request body

| Field    | Type   | Required | Validation          | Description                       |
|----------|--------|----------|---------------------|-----------------------------------|
| `reason` | string | yes      | `string`, min 5     | Mandatory reason for the reversal. |

### Example request

```
POST /api/v1/payments/42/reverse
Authorization: Bearer <sanctum-token>
Content-Type: application/json

{
  "reason": "Payment captured against the wrong loan"
}
```

### Example response — HTTP 200

```json
{
  "success": true,
  "message": "Payment reversed successfully.",
  "data": {
    "id": 42,
    "payment_number": "VATI-PAY-2026-000007",
    "amount": "150000.00",
    "status": "reversed",
    "reversed_by": 1,
    "reversed_at": "2026-09-22T11:00:00.000000Z",
    "reversal_reason": "Payment captured against the wrong loan"
  }
}
```

### Error responses

| HTTP | Scenario | Example response |
|------|----------|------------------|
| 422  | Payment not `posted` (already reversed) | `{ "success": false, "message": "Only posted payments can be reversed." }` |
| 422  | `reason` missing or < 5 characters | `{ "success": false, "message": "The reason must be at least 5 characters.", "errors": { "reason": ["The reason field must be at least 5 characters."] } }` |

---

## End-to-end flow (typical usage)

1. **Post** the repayment:
   ```
   POST /api/v1/loans/5/payments        { "amount": 100000, "payment_method": "cash" }
   ```
   → `201`, capture `data.id` (e.g. `42`) and `data.payment_number`.

2. **Edit** if the amount was captured incorrectly:
   ```
   PATCH /api/v1/payments/42            { "amount": 150000, "reason": "Wrong amount entered" }
   ```

3. **Undo** if the payment should not exist at all:
   ```
   POST /api/v1/payments/42/delete               { "reason": "Posted against the wrong loan" }
   ```
   → payment `status` becomes `reversed` and the loan balances are restored.

---

## Notes for clients

- All expected monetary amounts should be sent as numbers (`150000` or `150000.00`); the API always
  accepts numeric strings too.
- Editing is idempotent-safe: it re-allocates from scratch, so repeated edits converge correctly.
- Reversal is final; to re-capture a reversed payment, call `POST /api/v1/loans/{loan}/payments`
  again (you may reuse the same `idempotency_key`-style semantics by sending a fresh id).
- Notifications (`payment_edited`, `payment_reversed`) are sent to the application's originators on
  every successful edit/reverse, and each operation is written to the activity log.