# VATI Microfinance Backend

Laravel 12 REST backend for VATI Microfinance Limited. MySQL is the production database; the automated test suite uses an isolated in-memory SQLite database.

## Implemented MVP

- Sanctum token authentication, Spatie roles/permissions, and activity audit logs
- Region, area, branch, staff/role, group, member, and KYC management
- Mandatory group lending with immutable membership history and one active membership per member
- Configurable loan products and authoritative server-side loan calculations
- Loan applications, assessment capture, submission, approval/rejection, and formal loan creation
- Server-derived application branch/group snapshots and configurable group-witness approval requirements
- Transactional disbursement and weekly/monthly repayment schedule generation
- Idempotent payments with interest/principal allocation and reversal-based correction
- Member security accounts and immutable security transactions
- Loan settlement, digital passbook, dashboard/PAR metrics, branch isolation, pagination, and search
- Schema support for guarantors, documents, family/assets/nominees, refinancing, group meetings/collections, payment provider callbacks, offline sync/device registration, cashbooks, and audit logs
- Responsive Laravel Blade administration portal using the VATI green/gold visual system
- Browser workflows for organization setup, staff accounts, groups, member/KYC registration, loan products, applications, witnesses, approvals, disbursement, repayments, reversals, security accounts, settlements, and portfolio reports

Payment-provider credentials and production-specific fee/interest rules are intentionally not hardcoded. Confirm the exact VATI rules before production use.

## Requirements

- PHP 8.3+
- Composer 2
- MySQL 8+ (InnoDB)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create the database and update `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vati_microfinance
DB_USERNAME=root
DB_PASSWORD=your_password
```

Optionally set `VATI_ADMIN_EMAIL` and `VATI_ADMIN_PASSWORD` to create the first super administrator, then run:

```bash
php artisan migrate --seed
php artisan serve
```

The API base URL is `/api/v1`. Log in at `POST /api/v1/auth/login` and send the returned token as `Authorization: Bearer <token>`.

The web administration portal is available at `/login`. It uses secure Laravel session authentication and applies the same role, permission, branch-access, and transactional business rules as the API.

Member registration requires both `branch_id` and an active `group_id` belonging to that branch. Loan-application clients submit `member_id`, product, amount, and duration; the backend derives `branch_id` and `group_id` from the member’s active membership.

Group-lending endpoints include:

```text
GET  /api/v1/groups/{group}/dashboard
GET  /api/v1/groups/{group}/loans
GET  /api/v1/groups/{group}/applications
GET  /api/v1/groups/{group}/collections
GET  /api/v1/groups/{group}/meetings
GET  /api/v1/groups/{group}/members
GET  /api/v1/loan-applications/{application}/group-witnesses
POST /api/v1/loan-applications/{application}/group-witnesses
```

The number of confirmed witnesses required before approval is configured with `loan_products.required_group_witnesses`.

## Verification

```bash
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api/v1
```

## Production notes

Use HTTPS, `APP_DEBUG=false`, queue workers, the Laravel scheduler, encrypted backups, private KYC storage, MFA for privileged accounts, and provider callback signature validation. Run at least one end-to-end migration and workflow test against the target MySQL version before deployment.
