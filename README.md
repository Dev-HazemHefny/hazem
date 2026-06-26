# SaaS Subscription Management System

Multi-tenant Laravel backend for subscription billing, invoicing, payments, double-entry accounting (deferred revenue + revenue recognition), and financial reports.

## Architecture — Service / Action Pattern

Business logic lives in **`app/Services/`**. Controllers (API + Web) stay thin and reusable.

```
HTTP Request
    │
    ▼
Controller (Api/V1 or Web)     ← validation via FormRequest, JSON via Resources
    │
    ├──► Action (app/Actions/)   ← thin orchestrator for jobs / multi-step flows
    │         │
    │         ▼
    └──► Service (app/Services/) ← heavy domain logic, transactions, locks
              │
              ▼
         Models + PostgreSQL RLS
```

### Services (`app/Services/`)

| Namespace | Responsibility |
|-----------|----------------|
| `Tenancy/` | Registration, auth, timezone |
| `Billing/` | Plans, customers, subscriptions, invoices, billing cycle, plan changes |
| `Payment/` | Payment recording, idempotency, overpayment guard |
| `Accounting/` | COA, journal entries, revenue recognition |
| `Subscription/` | Past-due marking |
| `Reporting/` | Income statement, balance sheet |
| `System/` | Health checks |

### Actions (`app/Actions/`)

Single-purpose classes that delegate to services — used by jobs, seeders, and complex controller flows:

- `RegisterTenantAction`, `RunBillingCycleAction`, `RecordPaymentAction`
- `RecognizeSubscriptionRevenueAction`, `MarkPastDueSubscriptionsAction`
- `PostJournalEntryAction`, `ReverseJournalEntryAction`, `ChangeSubscriptionPlanAction`

### Controllers

- **API:** `app/Http/Controllers/Api/V1/*` → `/api/v1/*`
- **Web:** `app/Http/Controllers/Web/*` → `/web/v1/*` (extends API controllers — same services)

## Quick Start

### Docker (PostgreSQL + RLS)

> **Windows:** If you have a local PostgreSQL service on port 5432, Docker uses **5433** by default to avoid conflicts.

```bash
docker compose up -d
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Demo credentials

```
Email:    demo@acme.com
Password: DemoPass123!
```

## Live Demo

<!-- Add your hosted deployment URL below when ready -->
**Demo URL:** _pending — add your link here_

There is no hosted public deployment bundled with this repository by default. To run locally:

```bash
docker compose up -d
php artisan migrate --seed
php artisan serve
```

| Resource | URL |
|----------|-----|
| API base | http://localhost:8000/api/v1 |
| Swagger UI | http://localhost:8000/api/documentation |
| OpenAPI spec | http://localhost:8000/api/documentation/openapi.yaml |
| Postman collection | http://localhost:8000/docs/postman/collection.json |
| Health | http://localhost:8000/api/v1/health |

Import the Postman collection, run **Login (Demo)**, then explore the grouped folders.

## API Documentation

- **Swagger UI:** `/api/documentation` — interactive explorer
- **OpenAPI 3.0:** `/api/documentation/openapi.yaml` (source: `docs/openapi.yaml`)
- **Postman:** `/docs/postman/collection.json` (source: `docs/postman/collection.json`)

## API

Base URL: `/api/v1`

| Area | Endpoints |
|------|-----------|
| Auth | `POST /auth/register-tenant`, `/auth/login` (requires `tenant_slug`), `/auth/logout`, `GET /auth/me` |
| Plans | Full CRUD |
| Customers | Full CRUD (soft delete) |
| Subscriptions | Full CRUD (soft delete) + `POST /subscriptions/{id}/cancel`, `POST /subscriptions/{id}/change-plan` (mid-cycle proration) |
| Invoices | List, show |
| Payments | `POST /invoices/{id}/payments` |
| Reports | Income statement, balance sheet |
| Jobs | `POST /jobs/run-billing`, `/jobs/run-revenue-recognition` (requires `X-Cron-Secret` only — platform cron) |

Response envelope: `{ "success": true|false, "data"|"error": ..., "meta": { "request_id" } }`

## Accounting (Accrual)

- **Billing:** DR Accounts Receivable / CR Deferred Revenue
- **Payment:** DR Cash / CR Accounts Receivable
- **Recognition:** DR Deferred Revenue / CR Subscription Revenue (independent of payment)

Retained Earnings in MVP equals cumulative Subscription Revenue (no expense accounts).

## Multi-Tenancy

- Shared DB + `tenant_id` + PostgreSQL RLS (production)
- Runtime connects as `app_user` (non-owner) — RLS enforced
- Migrations run as `migrate_user`
- `TenantContext::runAs()` wraps requests/jobs with `SET LOCAL app.current_tenant`

## Tests

```bash
# Fast suite (SQLite in-memory) — skips RLS tests if Docker is not running
php artisan test

# Full suite on PostgreSQL + RLS (requires: docker compose up -d)
composer test:pgsql

# Both suites
composer test:all
```

Coverage includes:

| Area | Tests |
|------|-------|
| Tenant registration & health | `TenantRegistrationTest`, `HealthCheckTest` |
| Multi-tenant isolation | `TenantIsolationApiTest` (cross-tenant API access blocked) |
| PostgreSQL RLS | `TenantRlsTest` (auto-runs when Docker PostgreSQL is up) |
| Auth | `AuthLoginTest` (tenant-scoped login) |
| Accounting flow | Invoice journal (AR + deferred), payments (cash + AR), revenue recognition (paid & unpaid), yearly schedules, balanced balance sheet |
| Payment guards | Idempotency, overpayment rejection, partial payments |
| Subscription lifecycle | Cancel, delete (soft), open-invoice guard, mid-cycle plan change with proration |
| Plan change / proration | `PlanChangeTest`, `ProrationCalculatorTest` |
| Journal draft | Balance assertion unit tests |

`TenantRlsTest` uses `pgsql_migrate` for schema refresh and `app_user` for RLS assertions. It runs automatically in the default suite when PostgreSQL is reachable on `DB_PORT` (default **5433**). **Warning:** RLS tests run `migrate:fresh` on the PostgreSQL database.

## Out of Scope (MVP)

- Payment gateway integration (manual payment recording only)
- Cross-interval plan changes (monthly ↔ yearly)

## Manual job triggers

```bash
curl -X POST http://localhost:8000/api/v1/jobs/run-billing \
  -H "X-Cron-Secret: $CRON_SECRET"
```

See `SAAS_SUBSCRIPTION_IMPLEMENTATION_PLAN.md` for the full specification.
