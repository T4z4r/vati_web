# VATI Microfinance Backend

Laravel 12 REST backend for VATI Microfinance Limited. MySQL is the production database; the automated test suite uses an isolated in-memory SQLite database.

## Implemented MVP

- Sanctum token authentication, Spatie roles/permissions, and activity audit logs
- Region, area, branch, staff/role, group, member, and KYC management
- Configurable loan products and authoritative server-side loan calculations
- Loan applications, assessment capture, submission, approval/rejection, and formal loan creation
- Transactional disbursement and weekly/monthly repayment schedule generation
- Idempotent payments with interest/principal allocation and reversal-based correction
- Member security accounts and immutable security transactions
- Loan settlement, digital passbook, dashboard/PAR metrics, branch isolation, pagination, and search
- Schema support for guarantors, documents, family/assets/nominees, refinancing, group meetings/collections, payment provider callbacks, offline sync/device registration, cashbooks, and audit logs

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

## Verification

```bash
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api/v1
```

## Production notes

Use HTTPS, `APP_DEBUG=false`, queue workers, the Laravel scheduler, encrypted backups, private KYC storage, MFA for privileged accounts, and provider callback signature validation. Run at least one end-to-end migration and workflow test against the target MySQL version before deployment.
