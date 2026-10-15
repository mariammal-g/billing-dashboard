# Subscription Billing & Usage-Metering System

A multi-tenant SaaS billing backend built with Laravel, MySQL, and Redis-compatible queues. It supports merchant plans, customer subscriptions, usage-event ingestion, daily aggregation, billing calculations, and a dashboard API.

> **Note:** Verify route names, commands, environment variables, and invoice/plan-change functionality against the actual code before submitting. This README documents the architecture and design assumptions for the project.

## Table of Contents

- [Features](#features)
- [Architecture Summary](#architecture-summary)
- [Data Model](#data-model)
- [Design Decisions and Assumptions](#design-decisions-and-assumptions)
- [Requirements](#requirements)
- [Setup and Run](#setup-and-run)
- [Environment Configuration](#environment-configuration)
- [Database Setup and Seed Data](#database-setup-and-seed-data)
- [Queue Worker and Aggregation](#queue-worker-and-aggregation)
- [API Endpoints](#api-endpoints)
- [Usage Ingestion Example](#usage-ingestion-example)
- [Dashboard API](#dashboard-api)
- [Billing Calculation](#billing-calculation)
- [Testing](#testing)
- [Scaling and Performance](#scaling-and-performance)
- [Security Notes](#security-notes)
- [Troubleshooting](#troubleshooting)

## Features

- Merchant-scoped plans and customers.
- Customer subscriptions and dated subscription segments that preserve pricing terms.
- Usage ingestion with idempotency keys.
- Asynchronous daily usage aggregation.
- Billing calculations for base charges and overage.
- Proration support where implemented by the invoice logic.
- Dashboard API for current-cycle usage, projected overage, top customers, churn-risk signals, and daily usage trends.
- Database indexes and uniqueness constraints to support high-volume usage ingestion.

## Architecture Summary

```text
Client / Merchant
       |
       | POST /api/usage
       v
Usage API + validation
       |
       v
RecordUsageAction
       |
       | transaction + idempotency check
       v
usage_events (MySQL)
       |
       | dispatch after commit
       v
Queue (Redis-compatible)
       |
       v
AggregateDailyUsageJob
       |
       v
daily_usage (MySQL)
       |
       +-----------------------+
       |                       |
       v                       v
Billing / Invoice logic    GET /api/dashboard
                               |
                               v
                         React dashboard
```

| Component | Responsibility |
|---|---|
| API/controller | Validates requests, applies rate limiting, returns HTTP responses. |
| `RecordUsageAction` | Resolves the customer and applicable subscription segment, enforces idempotency, and persists events. |
| `usage_events` | Detailed source of truth for accepted usage events. |
| `AggregateDailyUsageJob` | Aggregates an event into a customer/segment/day bucket. |
| `daily_usage` | Pre-aggregated totals for reporting and billing queries. |
| Subscription segments | Preserve effective dates, plan, allowance, price, and overage rate. |
| Billing/invoice logic | Calculates base charges, overage, and supported proration. |
| Dashboard endpoint | Returns reporting data as JSON. |
| Redis-compatible service | Provides queue and/or cache infrastructure, based on `.env`. |

## Data Model

Key relationships:

- **Merchant → Plans:** a merchant owns plans.
- **Merchant → Customers:** customers belong to a merchant.
- **Customer → Subscriptions:** a customer can have subscriptions over time.
- **Subscription → Subscription segments:** each segment records effective dates and commercial terms.
- **Usage event → Customer, Subscription, Subscription segment:** an accepted event is assigned to the terms applicable at `occurred_at`.
- **Daily usage → Customer, Subscription segment, date:** stores aggregate units for reporting and billing.

Core tables include `merchants`, `plans`, `customers`, `subscriptions`, `subscription_segments`, `usage_events`, and `daily_usage`. Invoice tables may also exist, depending on the current implementation. Migrations are the source of truth for exact columns, foreign keys, indexes, and constraints.

## Design Decisions and Assumptions

### Tenant isolation

Records are scoped by `merchant_id`. In production, derive the merchant from authenticated credentials or a verified API key; do not trust a client-supplied `merchant_id` as the authorization boundary. The development Postman example includes it for convenience.

### Usage event idempotency

Each logical usage request has an idempotency key. Retrying the same request must not create a second billable event. Enforce this with a database unique constraint scoped to the appropriate tenant/key identity and handle duplicate-key races safely.

Persist the event before dispatching its aggregation job. Dispatch after commit so the worker cannot try to read an uncommitted event.

**Important aggregation rule:** a plain additive upsert is not idempotent if the same job executes twice. Use a processed-event marker, aggregation ledger, or transactional strategy so each event contributes only once. Another option is rebuilding an affected daily bucket from raw events.

### Subscription segments and historical pricing

A segment stores the plan, base price, included units, overage rate, and effective dates. Usage is assigned to the segment effective at the event timestamp. This preserves historical billing terms after plan changes.

Use a consistent date-boundary convention (inclusive/exclusive) and prevent overlapping segments.

### Billing cycle

The sample dashboard implementation uses calendar-month boundaries. A production billing engine should use the persisted billing period for each subscription when billing dates can differ between customers.

### Money and overage

Store money as integer minor units rather than floating-point values. The existing frontend contract uses `projected_overage_revenue_cents`; if billing in INR, ensure this field consistently represents paise (100 paise = ₹1). Consider renaming it to `projected_overage_amount_minor` to avoid currency ambiguity.

```text
overage_units = max(0, billable_units - included_units)
overage_amount_minor = overage_units * overage_rate_minor_per_unit
```

Taxes, discounts, credits, and payment-provider fees are excluded unless explicitly implemented.

### Proration

For a mid-cycle start or plan change, prorate the applicable base charge if the invoice logic supports it. Document whether proration uses calendar days or seconds and how rounding works. Use saved segment pricing rather than retroactively applying the latest plan to past usage.

### Eventual consistency

Usage events are accepted synchronously; daily aggregates update asynchronously. The dashboard may briefly lag behind newly accepted events. Before finalizing an invoice, wait for relevant aggregation to complete or reconcile against raw events.

### Churn-risk signal

For the dashboard, churn risk is a heuristic: current-period usage is below 50% of previous-period usage, and previous-period usage is greater than zero. This is an indicator, not a prediction or confirmed cancellation. For incomplete current periods, compare equivalent elapsed periods.

### Dashboard scope and system status

The development dashboard controller may select the first merchant. This is not safe for production: scope queries to the authenticated merchant and ensure joins are tenant-safe.

A configured queue driver does not prove a worker is running; a configured cache driver does not prove the cache service is healthy. Report these as configuration details unless actual health checks are implemented.

### High-volume usage

The design anticipates tens of millions of usage events. Keep synchronous writes small, index idempotency and lookup columns, aggregate asynchronously in batches, and monitor queue lag. Consider partitioning or archival only after measuring performance and confirming support in the chosen MySQL version.

## Requirements

- PHP version supported by the Laravel version in `composer.json`.
- Composer.
- MySQL.
- Redis or a Redis-compatible service for Redis-backed cache/queues.
- Node.js/npm only if running the optional React dashboard.
- A running queue worker for asynchronous aggregation.

Check versions:

```bash
php -v
composer --version
php artisan --version
```

## Setup and Run

Commands below assume the project path is `C:\laragon\www\CommonAPI\src`.

### 1. Install dependencies

```bash
cd C:\laragon\www\CommonAPI\src
composer install
```

If `.env` does not exist:

```bash
copy .env.example .env
php artisan key:generate
```

Do not overwrite an existing `.env` containing your local settings.

### 2. Configure MySQL

Create a database (for example, `commonapi`) and configure `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=commonapi
DB_USERNAME=root
DB_PASSWORD=
```

Use the credentials configured for your local MySQL/Laragon installation.

### 3. Run migrations

```bash
php artisan migrate
```

Only if you intend to delete local data and rebuild from scratch:

```bash
php artisan migrate:fresh --seed
```

### 4. Configure cache and queue

Example Redis-compatible configuration:

```dotenv
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
```

Install Predis if needed:

```bash
composer require predis/predis
```

A Redis-compatible server must also be installed and running. Installing the PHP client alone does not start the server. On Windows/Laragon, use a supported Redis-compatible service such as Memurai, or choose a different Laravel-supported local queue/cache driver.

### 5. Clear cached configuration

```bash
php artisan optimize:clear
```

### 6. Start Laravel

```bash
php artisan serve
```

The default local URL is `http://127.0.0.1:8000`.

### 7. Start the queue worker

In a separate terminal:

```bash
php artisan queue:work
```

Keep the worker running while testing asynchronous aggregation. Production deployments should supervise and monitor workers.

## Environment Configuration

Review at least these `.env` settings:

| Variable | Purpose |
|---|---|
| `APP_ENV` | Application environment. |
| `APP_DEBUG` | Enable only in local development; disable in production. |
| `DB_*` | MySQL connection. |
| `CACHE_STORE` | Laravel cache backend. |
| `QUEUE_CONNECTION` | Queue backend. |
| `REDIS_*` | Redis-compatible connection settings, if used. |

Never commit secrets, production credentials, API keys, or customer data.

## Database Setup and Seed Data

Use the project's existing seeders to create this relationship chain:

1. Merchant
2. Plan belonging to the merchant
3. Customer belonging to the merchant
4. Subscription linking the customer and plan
5. Subscription segment with effective dates and copied pricing terms

When testing usage ingestion, set `occurred_at` to a timestamp covered by the relevant subscription segment. An event outside all valid segments should be rejected instead of being assigned incorrect billing terms.

Make seeders repeatable where practical; avoid creating duplicate merchants and customers on every run. Add several customers and varied usage amounts to test rankings, overage, and churn-risk output.

## Queue Worker and Daily Aggregation

Expected flow:

1. Validate the request and idempotency key.
2. Resolve the customer and subscription segment for the event timestamp.
3. Persist the event transactionally.
4. Dispatch the aggregation job after commit.
5. Update the daily bucket safely.
6. Monitor failed jobs and reconcile aggregates against raw events.

Useful commands (availability depends on Laravel version and queue setup):

```bash
php artisan queue:work
php artisan queue:failed
php artisan queue:retry all
```

For failures, inspect `storage/logs/laravel.log` and the failed-job details before retrying.

## API Endpoints

Expected endpoints:

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/usage` | Ingest a usage event. |
| `GET` | `/api/dashboard` | Return dashboard metrics. |

Confirm actual route names and middleware:

```bash
php artisan route:list --path=api
```

Laravel normally adds the `/api` prefix to routes in `routes/api.php`; avoid adding the prefix twice.

## Usage Ingestion Example

Postman request:

```http
POST http://127.0.0.1:8000/api/usage
Content-Type: application/json
Accept: application/json
Idempotency-Key: 7f81a8d2-1234-4567-8901-123456789abc
```

Example JSON body (replace IDs and timestamp with valid records in your database):

```json
{
  "merchant_id": 1,
  "customer_id": 1,
  "units": 100,
  "occurred_at": "2026-10-15T10:00:00Z"
}
```

- Reuse the same idempotency key only when retrying the same logical request.
- Ensure the customer belongs to the relevant merchant.
- `units` should be a positive integer unless adjustments are explicitly supported.
- `occurred_at` must fall within a valid subscription segment.
- A successful ingestion response does not necessarily mean the daily aggregate has already updated; the queue may still be processing.

## Dashboard API

Request:

```http
GET http://127.0.0.1:8000/api/dashboard
Accept: application/json
```

Expected response contract:

```json
{
  "data": {
    "merchant_name": "Acme Corp",
    "current_cycle_usage": 0,
    "included_units": 0,
    "projected_overage_revenue_cents": 0,
    "active_plan": {
      "name": "Growth",
      "billing_cycle": "monthly"
    },
    "top_customers": [],
    "churn_risk": [],
    "daily_usage": [],
    "system_status": {
      "cache": "redis",
      "cache_ttl": "configured",
      "aggregation": "redis",
      "batch_size": null,
      "rate_limit": "configured"
    }
  }
}
```

This is a response contract, not seed data. Populate it from actual database queries and configuration. Adapt the controller if your migrations use different column names or your authentication model differs.

### Optional React dashboard

If the React dashboard is in a separate Vite project, set its `.env`:

```dotenv
VITE_API_BASE_URL=http://127.0.0.1:8000/api
VITE_DASHBOARD_ENDPOINT=/dashboard
```

Then run:

```bash
npm install
npm run dev
```

Configure Laravel CORS for the local Vite origin (usually `http://localhost:5173`). Send the required bearer token or use the authentication flow configured by the application.

## Billing Calculation

At a high level:

```text
invoice total =
    base charge (including applicable proration)
  + overage charge
  + taxes
  - discounts and credits
```

```text
overage units = max(0, billable usage - included units)
overage charge = overage units × overage rate per unit
```

Use the segment's saved pricing terms for each applicable period. If a subscription changes plans mid-cycle, calculate the charge and usage for each applicable segment rather than applying the latest plan retroactively.

Invoice generation should be idempotent for a subscription and billing period. Use a transaction and a unique constraint on the invoice's billing-period identity so retries do not create duplicate invoices or lines.

## Testing

Run tests:

```bash
php artisan test
```

Recommended coverage:

- Valid usage event is persisted.
- Duplicate idempotency key does not create another billable event.
- Invalid merchant/customer association is rejected.
- Usage outside a valid subscription segment is rejected.
- Aggregation creates the expected daily total.
- Retrying an aggregation job does not double-count an event.
- Overage is zero below the allowance and correct above it.
- Proration follows the documented rule.
- Invoice generation is idempotent.
- Dashboard queries are tenant-scoped.
- Dashboard handles no active subscription and empty usage.
- Rate limiting applies to usage ingestion.

Use a dedicated test database and isolated queue/cache services. Never run destructive test setup against production data.

## Scaling and Performance

For tens of millions of usage events:

- Add indexes based on query patterns: idempotency identity, merchant/customer lookup, subscription/segment lookup, and event timestamp.
- Keep synchronous writes small and use bulk operations where appropriate.
- Aggregate asynchronously in controlled batches.
- Make jobs safe to retry.
- Monitor queue depth, processing duration, failures, database latency, and aggregation lag.
- Paginate operational endpoints; avoid returning raw event history in dashboard responses.
- Consider time-based partitioning or archival only after measuring performance and checking MySQL version support.
- Reconcile daily aggregates against raw events.

Do not add denormalized counters or partitioning without a clear correctness and reconciliation strategy.

## Security Notes

- Derive tenant identity from authenticated credentials in production.
- Validate and rate-limit usage ingestion.
- Enforce idempotency with a database unique constraint, not only an application-level existence check.
- Authorize plan, customer, subscription, and invoice operations.
- Disable debug output in production.
- Protect dashboard and billing endpoints with authentication.
- Keep API keys and secrets out of source control and logs.
- Monitor failed jobs and reconcile aggregates before finalizing invoices.
- Treat system-status fields as informational unless backed by real health checks.

## Troubleshooting

### `Class "Redis" not found`

The PHP Redis extension (`phpredis`) is unavailable to the active PHP runtime. Either install/enable the matching extension or use Predis with `REDIS_CLIENT=predis` and install the Composer package.

### Redis connection refused

The client is configured, but no Redis-compatible server is listening at the configured host and port. Start the service and verify `REDIS_HOST` and `REDIS_PORT`.

### `Rate limiter [usage] is not defined`

Define the named `usage` limiter in the appropriate service provider/bootstrap location for your Laravel version and ensure the route uses the matching throttle middleware.

### Dashboard SQL syntax error near `usage`

Avoid `usage` as a SQL alias in this query. Use an alias such as `total_usage`, order by that alias, and map it to the API response property `usage`.

### Dashboard shows zero or empty arrays

Check that:

- The event timestamp is inside the current billing period.
- The queue worker has processed the event.
- `daily_usage` contains the expected date/customer/merchant rows.
- The dashboard query is scoped to the intended merchant.
- The subscription segment covers the event timestamp.
- The JSON fields match the frontend's expected response contract.

### Usage is stored but daily totals do not change

Check that the queue worker is running, inspect `storage/logs/laravel.log`, check failed jobs, and verify that the aggregation job is dispatched after commit and safe to retry.

## Final Implementation Checklist

- [ ] Migrations create the required tables, indexes, and unique constraints.
- [ ] Seeders create merchant → plan → customer → subscription → segment.
- [ ] Usage ingestion validates tenant, timestamp, units, and idempotency.
- [ ] Queue worker runs and aggregation is safe to retry.
- [ ] Billing uses historical segment pricing.
- [ ] Invoice generation is idempotent, if implemented.
- [ ] Dashboard is tenant-scoped and returns the documented response shape.
- [ ] Tests cover retries, duplicate requests, billing, and tenant isolation.
- [ ] README commands and endpoint names match the actual checkout.
