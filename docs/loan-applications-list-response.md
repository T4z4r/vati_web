# Loan applications list response

## Endpoint

`GET /api/v1/loan-applications`

Send `Authorization: Bearer <token>` and `Accept: application/json`.

Supported query parameters: `status`, `member_id`, `reviewed_today`, `page`, and `per_page` (default 20, maximum 100). Results are ordered newest first. `reviewed_today=true` filters reviewed applications assigned to the authenticated user.

## New field

Each entry in `data` now includes **`amount_receivable`**, directly beside `requested_amount`.

| Field | Type | Meaning |
| --- | --- | --- |
| `amount_receivable` | decimal string or `null` | Amount available after deducting security and fees from the application's requested principal. |
| `calculator_breakdown.amount_receivable` | decimal string | Existing nested equivalent, retained for compatibility when the breakdown is available. |

The two fields have the same value. The backend uses saved calculator fee/security values when available; otherwise it calculates using the loaded product and application duration. If no valid breakdown can be produced, the top-level field is `null`, not zero.

```text
amount_receivable = requested_amount - security_amount - charges
charges = processing_fee + insurance_fee + vat
```

Example: requested amount 1,000,000, security 100,000, and charges 60,000 yields `"840000.00"`.

This is the application-stage figure. For actual disbursement, fetch the loan's own `amount_receivable`; an approved or recommended amount may differ from the original request.

## Response structure

The response retains Laravel's paginated envelope: `data` is an array, `links` contains navigation URLs, and `meta` contains pagination information. There is no new wrapper or `success` field on this list endpoint.

The following valid JSON example shows selected fields; existing application fields and nested member/product data remain unchanged:

```json
{
  "data": [
    {
      "id": 182,
      "application_number": "VATI-LAF-2026-000182",
      "application_type": "main",
      "requested_amount": 1000000,
      "amount_receivable": "840000.00",
      "recommended_amount": null,
      "duration_months": 6,
      "status": "submitted",
      "calculator_breakdown": {
        "principal": "1000000.00",
        "interest": "0.00",
        "processing_fee": "30000.00",
        "insurance_fee": "20000.00",
        "vat": "10000.00",
        "security_amount": "100000.00",
        "charges": "60000.00",
        "amount_receivable": "840000.00",
        "total_repayment": "1000000.00"
      }
    }
  ],
  "links": {
    "first": "https://example.test/api/v1/loan-applications?page=1",
    "last": "https://example.test/api/v1/loan-applications?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "https://example.test/api/v1/loan-applications",
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```

Other list fields include `member`, `product`, `recommended_duration_months`, `risk_level`, assignment/creator information, financing fields, purpose, business summary, consent/signature fields, `requirements`, `witness_progress`, submission time, and review attempt. Additional relations are included only when loaded; the standard list does not load group, branch, or loan details. Pagination metadata also includes Laravel's page-link entries.

Clients can read `response.data[index].amount_receivable` without traversing `calculator_breakdown`. Treat `null` as unavailable and preserve decimal precision. Existing field types are unchanged; the new amount is always a two-decimal string when available.

The same additive field appears wherever `LoanApplicationResource` is used, including group application lists. No database migration is required.
