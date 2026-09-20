# Loan fees, net disbursement, and member signatures

## Loan fees and amount receivable

The amount issued to the member is now the net amount after both security and fees:

```text
charges = processing_fee + insurance_fee + vat
amount_receivable = principal - security_amount - charges
disbursement.amount = amount_receivable
```

On successful issuance, the loan's saved `calc_security_amount` is automatically credited to the member's security account as a deposit linked to the loan. Existing balances are preserved; an account is created when needed. The transaction records the disbursement date, processing user, and before/after balances. Zero security creates no ledger entry. Issuance and the security credit commit or roll back together, and repeated disbursement attempts cannot credit security again. This applies to both API and web disbursement. Previously issued loans are not backfilled automatically.

Each fee is rounded to two decimal places before summing. VAT retains the existing product rule: its percentage is applied to principal. Security is a separate deduction and is not counted twice in charges. Deductions exceeding principal are rejected.

Example in TZS:

| Item | Amount |
| --- | ---: |
| Principal | 1,000,000.00 |
| Processing fee (3%) | 30,000.00 |
| Insurance (2%) | 20,000.00 |
| VAT (1%) | 10,000.00 |
| Total fees and charges | 60,000.00 |
| Security (10%) | 100,000.00 |
| Amount receivable / amount issued | **840,000.00** |

Approval already saves the approved amount's calculator breakdown on the loan. Disbursement now uses those saved charges and security, not the current product settings or a client-supplied amount. The audit entry records the net amount and deductions. Principal, repayment schedules, and outstanding debt retain their existing meaning.

Loan API responses expose `processing_fee`, `insurance_fee`, `vat`, `security_amount`, `charges`, `amount_receivable`, and `transaction_charges`, alongside `total_fees_and_vat` and the calculator breakdown. A loaded `disbursement` includes its actual `amount`. Monetary additions are decimal strings. The loan page includes a fees-and-charges panel; the application preview and calculator use the same net formula.

Portfolio “total issued” and API management disbursement totals sum completed disbursement records, rather than counting approved principal as money already paid out.

### Existing records

Existing disbursements are historical records and are not rewritten. Review any earlier over-disbursements separately. Disbursement requires a positive saved `calc_amount_receivable` consistent with the loan's saved principal, fees, and security. A missing or outdated saved receivable returns 409 and must be reviewed/corrected before issuance. The loan API computes its breakdown from saved values instead of recalculating with today's product settings.

### Disbursement API contract

`POST /api/v1/loans/{id}/disburse` requires `amount` copied from the backend's `amount_receivable`, plus `method`. Existing recipient, reference, and date fields remain supported. Amounts must be positive numeric values with at most two decimal places. Missing, malformed, zero, or negative submitted amounts return 422.

Inside a database transaction, the service locks and reloads the loan, rejects any existing disbursement, verifies the saved receivable, and compares the submitted amount with that saved value. A mismatch or repeat attempt returns 409. No disbursement or repayment schedule is created on rejection. The saved amount, never the submitted value, is used for issuance. Shared web/internal callers may omit `amount`; any supplied value is still checked.

Success returns 201 with updated loan data in `data`, including its `id`, `status: active`, `amount_receivable`, `issued_amount`, and `disbursement.amount`. Clients that previously treated `data` as a disbursement record should now read that record from `data.disbursement`.

## Member signature upload

Send multipart data to `POST /api/v1/members/{member}/documents`:

| Field | Value |
| --- | --- |
| `document_type` | `signature` |
| `file` | Binary PNG upload |
| `description` | Optional text, at most 1,000 characters |

Bearer authentication is required. API role and branch restrictions remain disabled per the existing project decision. Downloads and deletions verify that the document belongs to the member in the URL. Web access retains its existing permission checks.

PNG type is detected from file contents, so a multipart content type of `application/octet-stream` is accepted for actual PNG files. Dimension checks run before decoding; corrupt PNGs, SVGs, and renamed non-images are rejected. Defaults are 2 MiB and 4,000,000 pixels, configurable using `SIGNATURE_MAX_KILOBYTES` and `SIGNATURE_MAX_PIXELS`. PHP GD is required for PNG decoding.

Signatures use the private `signatures` disk, rooted at `storage/app/private/member-signatures`, with UUID filenames. Existing non-signature documents retain their storage behavior. Metadata records the server-derived uploader, detected MIME, byte count, SHA-256, and `uploaded` status. Uploading does not verify the signer's identity.

Creation returns 201 with a document in `data`; identical retries return 200 with the existing document. Different bytes when an active signature exists return 409. Member-row locking and a unique active-signature key prevent duplicate current records. Staged files are discarded when insertion fails or a retry/conflict is detected.

The ordinary document list includes active signatures. Responses include `id`, `document_type`, `file_name`, `mime_type`, `size_bytes`, `status`, and `created_at`. Private signatures use authenticated download URLs, not public-storage URLs. Downloads return file bytes with `Cache-Control: private, no-store`.

Deletion soft-deletes the signature and audits the action; metadata is retained even if `force` is sent. Failed file removal remains tracked for retry. Signatures referenced by a loan witness cannot be deleted (409). Witness `signature_path` values must resolve to that witness member's current signature and are linked through `signature_document_id`; arbitrary client paths are rejected. Automatic replacement is not provided.

### Deployment and cleanup

Run `php artisan migrate` to add signature metadata and the witness document reference. Keep the Laravel scheduler running (`php artisan schedule:run` every minute). The hourly `signatures:cleanup` task retries pending deletion and removes untracked files older than 24 hours. It can also be run manually with `php artisan signatures:cleanup`.

### Verification

Focused feature tests live in `MemberSignatureTest` and `LoanReceivableTest`. Run:

```sh
php artisan test tests/Feature/MemberSignatureTest.php tests/Feature/LoanReceivableTest.php
```
