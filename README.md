# ContentPass

ContentPass is a REST API for a subscription-based content platform — creators publish gated content, and subscribers get access based on their plan tier. This is a **portfolio project**, built incrementally in public over a series of days; it is not a production system.

## Live Demo

> **TODO:** add the live Render URL here once deployed (e.g. `https://content-pass.onrender.com`), plus a screenshot or two (the frontend dashboard, `/api/v1/health`, and/or the Horizon dashboard). `render.yaml` and the [Deployment](#deployment) section below are prepped for this; the actual deploy is a manual step.

> **Status: Day 14+.** Implemented so far: JWT authentication, RBAC middleware, the core database schema, Redis-backed background jobs via Horizon, a Redis-cached content listing endpoint, a mock subscription/billing flow, a `docker compose up` local stack (backend + sibling frontend + MySQL + Redis), and Render deployment config. See [Implemented](#implemented) / [Planned](#planned) below for the full picture.

## Tech Stack

- **Framework:** Laravel 12 (PHP 8.2+)
- **Auth:** JWT via [tymon/jwt-auth](https://github.com/tymondesigns/jwt-auth)
- **Database:** MySQL locally / in Docker Compose; Postgres on Render (see [Deployment](#deployment) — no code changes needed for the switch)
- **Queues:** Redis, managed with [Laravel Horizon](https://laravel.com/docs/horizon)
- **Cache:** Redis
- **Testing:** PHPUnit
- **Code style:** Laravel Pint
- **Local dev:** Docker Compose (backend, queue worker, MySQL, Redis, and the sibling frontend)

## API Overview

All endpoints are versioned under `/api/v1`. Authenticated requests use `Authorization: Bearer <token>`. Full detail (params, status codes) is in the [Endpoints](#endpoints) table further down.

**Auth**
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/auth/register` | Create an account |
| POST | `/auth/login` | Exchange credentials for a JWT |
| POST | `/auth/refresh` | Refresh an expiring JWT |
| POST | `/auth/logout` | Invalidate the current token |
| GET | `/auth/me` | Get the authenticated user |

**Content**
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/content` | Paginated list of published content, with a per-user `accessible` flag |

**Plans**
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/plans` | Public list of available plans — browsable before signup, no auth needed |

**Subscriptions** *(mock billing — see [Subscriptions](#subscriptions) below)*
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/subscriptions` | Subscribe to a plan (cancels any existing active subscription first) |
| GET | `/subscriptions/current` | Get the current active subscription, or `null` if none |
| DELETE | `/subscriptions/current` | Cancel the current active subscription |

**Admin**
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/admin/ping` | Placeholder route proving the `admin`-only RBAC gate works |

**Misc**
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/health` | Health check |

## Project Structure

This is an API-only project — `routes/api.php` is the entry point, and the default Blade/web scaffolding has been stripped out. The `app/` folder is organized to feel domain-driven, so it scales cleanly as subscription/billing logic is added:

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/       # Versioned API controllers (AuthController, ContentController, ...)
│   │   └── Concerns/     # Shared controller traits (e.g. consistent JSON responses)
│   ├── Requests/         # Form Request validation classes (grouped by domain, e.g. Auth/)
│   └── Resources/        # API Resource transformers (response shaping)
├── Models/                # Eloquent models
├── Observers/             # Model observers (e.g. cache invalidation)
├── Services/              # Business logic that doesn't belong in a controller or model
└── Jobs/                  # Queued jobs
```

Why this shape:

- **`Controllers/Api/V1/`** — versioning from day one means a `V2` can be introduced later without breaking existing clients.
- **`Requests/`** — validation rules live outside controllers so controllers stay thin and requests stay testable/reusable.
- **`Resources/`** — response shaping is centralized, so the JSON contract (e.g. hiding internal fields) doesn't leak across controllers.
- **`Services/`** — business logic that doesn't belong in a controller or model, e.g. `ContentAccessService` handles fetching/caching published content and computing per-user access — keeping controllers as thin HTTP adapters rather than where logic accumulates.

## API Conventions

All JSON responses follow a consistent envelope:

```jsonc
// Success
{ "data": { ... }, "message": "..." }

// Failure
{ "error": "...", "message": "..." }
```

Login and register are rate-limited (5 requests/minute per IP) to slow down brute-force attempts.

## Authorization

Role checks are enforced by the `role` route middleware (`App\Http\Middleware\EnsureUserHasRole`, aliased as `role` in `bootstrap/app.php`). It reads the authenticated user's `role` column (`user` or `admin`) and compares it against the role(s) passed to the middleware.

To protect a route, stack it behind `auth:api` (so there's an authenticated user to check) and `role:<name>`:

```php
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::get('admin/ping', [AdminController::class, 'ping']);
});
```

Multiple roles can be allowed with `role:admin,editor`. If the user's role isn't in the list, the middleware short-circuits with:

```jsonc
// 403 Forbidden
{ "error": "forbidden", "message": "You do not have permission to access this resource." }
```

An unauthenticated request never reaches the role check — `auth:api` rejects it first with the standard `{ "error": "Unauthenticated", "message": "Unauthenticated." }` 401.

## Background Jobs

Queued work runs on Redis and is managed with [Laravel Horizon](https://laravel.com/docs/horizon). Right now there's one job: `SendWelcomeEmailJob`, dispatched from `AuthController::register()`, which sends a `WelcomeEmail` mailable (3 tries, exponential backoff, logs via `failed()` if all retries are exhausted).

**Running the worker locally:**

```bash
php artisan horizon      # preferred — also powers the dashboard's metrics
# or, as a plain fallback:
php artisan queue:work
```

> **Windows note:** Horizon's master process (`php artisan horizon`) requires the `pcntl` extension, which doesn't exist on native Windows PHP builds (it's POSIX-only — not just "disabled," genuinely unavailable). On Windows, use `php artisan queue:work` instead; it reads from the exact same Redis queue and is what was used to verify this feature end-to-end during development. Horizon still picks up and displays metrics for jobs processed this way. On Linux/macOS (or WSL/Docker on Windows), `php artisan horizon` works normally.

**Dashboard:** visit `/horizon` to see queue throughput, recent/failed jobs, and worker status. It's open with no auth check in `local` env; anywhere else, only users with `role = admin` can view it (see `App\Providers\HorizonServiceProvider::gate()`). Since this is a token-only API with no session-based web login, reaching that gate in a non-local environment currently requires whatever admin-facing auth flow gets built later — worth keeping in mind before relying on it in a real deployment.

**Seeing the emails:** `MAIL_MAILER=log` by default, so nothing is actually sent — rendered emails are appended to `storage/logs/laravel.log` instead. Register a user and check that file to see the welcome email.

## Caching

The published content list (`GET /api/v1/content`) is cached in Redis via `App\Services\ContentAccessService`:

- **What's cached:** the raw, unpaginated result of `Content::whereNotNull('published_at')->where('published_at', '<=', now())->get()` — the same for every user. It is deliberately cached *before* pagination and before the per-user `accessible` flag is computed, so one cache entry serves all users and all pages.
- **Cache key:** a single fixed key, `content:published` (`ContentAccessService::CACHE_KEY`) — not user-specific, since the underlying list doesn't vary per user.
- **TTL:** 300 seconds / 5 minutes (`ContentAccessService::CACHE_TTL_SECONDS`), via `Cache::remember()`.
- **Per-request work:** after the (possibly cached) list is fetched, the controller paginates it in memory and calls `ContentAccessService::isAccessibleTo()` for each item against the current user's plan level (`users.plan_id -> plans.level`, defaulting to level 0 with no plan). None of that is cached.
- **Invalidation:** `App\Observers\ContentObserver`, registered on the `Content` model in `AppServiceProvider::boot()`, calls `ContentAccessService::forgetCache()` on `created`, `updated`, and `deleted` — so the very next request after any content change re-queries the database instead of serving stale data.

## Subscriptions

Subscribing a user to a plan is handled by `App\Services\SubscriptionService`, used from `SubscriptionController`:

- **Subscribing** (`POST /api/v1/subscriptions`, body `{ "plan_id": ... }`) creates a new `Subscription` with `status=active`, `started_at=now`, `renews_at=now+30 days`, and sets `users.plan_id` to match. If the user already has an active subscription, it's canceled first (`status=canceled`, `canceled_at=now`) — this is how a plan upgrade/downgrade is modeled: there's no separate "swap plan" endpoint, subscribing to a new plan always supersedes the old one.
- **Canceling** (`DELETE /api/v1/subscriptions/current`) cancels the active subscription and resets `users.plan_id` to `null` (the free tier). Returns 404 if there's nothing active to cancel.
- **Reading** (`GET /api/v1/subscriptions/current`) returns the active subscription with plan details, or `null`.

**This is mock billing — there is no real payment gateway.** `POST /api/v1/subscriptions` simulates the *outcome* of a successful payment; it doesn't charge anyone or talk to any payment provider. A real integration (e.g. Stripe) would look different: the client would create a Checkout Session / PaymentIntent, and the `Subscription` row would be created from a webhook after the provider confirms payment actually succeeded — not synchronously from this request. `SubscriptionController::store()` has a comment marking exactly where that swap would happen.

## Setup

### Running with Docker (recommended)

**Prerequisites:**
- Docker and Docker Compose v2 (the `docker compose` command — not the old standalone `docker-compose` v1 script)
- The `content-pass-web` frontend cloned as a **sibling directory** next to this one:
  ```
  some-folder/
  ├── content-pass/        (this repo)
  └── content-pass-web/
  ```

Then, from inside `content-pass/`:

```bash
docker compose up
```

That's it — no `.env` to create by hand. `docker-compose.yml` builds this repo's `Dockerfile` for the `app` and `queue` services, and the entrypoint script (`docker/entrypoint.sh`) creates `.env` from `.env.example` on first start (if it isn't already there), generates `APP_KEY`/`JWT_SECRET` if they're missing, and runs `migrate --seed` before starting the server — every time, safely (migrations only apply what's pending, and both seeders use `updateOrCreate`).

**What comes up:**

| Service | URL | Notes |
|---|---|---|
| Backend API | http://localhost:8000/api/v1 | This repo |
| Horizon dashboard | http://localhost:8000/horizon | Open with no login in `local` env — see [Background Jobs](#background-jobs) |
| Frontend | http://localhost:3000 | Built from `../content-pass-web` |
| MySQL | internal only (`mysql:3306`) | Named volume `mysql-data` persists data across restarts |
| Redis | internal only (`redis:6379`) | Named volume `redis-data`; backs both the queue and the cache |

`queue` runs `php artisan horizon` instead of serving HTTP — it's a second container built from the *same* image as `app`, just with a different command, so Horizon jobs (like the Day 5 welcome email) actually process. Unlike on native Windows, `pcntl` is available inside the Linux container, so `php artisan horizon` runs for real here (see the note in [Background Jobs](#background-jobs)).

**Why `php artisan serve` instead of Nginx + PHP-FPM:** for a portfolio-scope local stack, a single `php:8.2-cli`-based image running the built-in server behind a published port is simpler to build, explain, and debug than a two-process (Nginx + PHP-FPM) container or a second Nginx service — with no meaningful downside for local dev. A production deployment would use PHP-FPM behind Nginx (or a managed PHP host); that's a deliberate scope cut for this project, not an oversight.

**Frontend API URL — two variables, not one:** Day 9's dashboard is server-rendered, so some API calls happen from the Node.js process inside the `frontend` container (SSR) while others (e.g. submitting the login form) happen from the user's browser. `NEXT_PUBLIC_*` variables get inlined into the browser bundle at build time, so `NEXT_PUBLIC_API_URL` has to be something the *browser* can reach — `http://localhost:8000/api/v1`, the port published to the host — since the browser has no way to resolve Docker's internal service names. The SSR calls, on the other hand, run inside the Docker network, where `localhost` means the `frontend` container itself, not the backend; those need the internal hostname, `http://app:8000/api/v1`, passed via a separate `API_URL` variable. **Caveat:** I haven't inspected or modified `content-pass-web`'s code from this repo, so this assumes its Day 9 SSR code reads `API_URL` (not the public one) for server-side fetches. If it instead uses `NEXT_PUBLIC_API_URL` universally, the SSR path will fail inside Docker and either that code or this compose file's variable needs to change.

**Rebuilding after code changes:** `docker compose up --build`. To reset the database entirely: `docker compose down -v` (this drops the named volumes, including `mysql-data`).

> **Verification note:** Docker itself wasn't available in the environment this was built in, so `docker compose config` / an actual build couldn't be run. What *was* verified: `docker-compose.yml` parses as valid YAML and the service/dependency graph matches what's described here; `docker/entrypoint.sh` has Unix line endings (enforced repo-wide by `.gitattributes`, so the shebang won't break in the container); and, most importantly, the Dockerfile's exact `composer install` command was dry-run locally against a copy of this repo with `tests/`, `.git`, `vendor/`, and `.env` stripped out (mirroring what `.dockerignore` actually excludes from the image) using `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` (those two extensions aren't present on this Windows host but are installed explicitly in the Dockerfile for the Linux container). That dry run caught two real issues before they could surface as build failures: `laravel/horizon` requires `ext-posix` in addition to `ext-pcntl` (only the latter was in my first draft), and `composer install --no-dev` would have broken the startup seed, since `DatabaseSeeder` calls `User::factory()`, which needs `fakerphp/faker` — a dev-only package. Both are fixed in the files below. What still needs a real Docker Engine to confirm: that the image actually builds end-to-end (system package availability, `docker-php-ext-install` succeeding, etc.), that `docker compose up` brings up a working stack, and that the frontend build/`API_URL` assumption above holds against the real `content-pass-web` code.

### Manual setup (without Docker)

**Prerequisites:** PHP 8.2+, Composer, MySQL running locally, Redis running locally.

```bash
git clone <repo-url> content-pass
cd content-pass

composer install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edit `.env` and set the values below for your local environment (all are already present in `.env.example`, grouped here by what they're for — note the defaults are now Docker-oriented, so `DB_HOST`/`REDIS_HOST` need changing for local, non-Docker use; see the inline comments in `.env.example`):

| Purpose | Variables |
|---|---|
| App | `APP_NAME`, `APP_URL` |
| Database (MySQL) | `DB_CONNECTION`, `DB_HOST` (→ `127.0.0.1`), `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (→ your local root password) |
| Redis (queue + cache) | `REDIS_CLIENT`, `REDIS_HOST` (→ `127.0.0.1`), `REDIS_PORT`, `REDIS_PASSWORD`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` |
| JWT | `JWT_SECRET` (generated by `jwt:secret` above), `JWT_TTL` |
| Mail | `MAIL_MAILER` (`log` by default — see [Background Jobs](#background-jobs)) |

> `REDIS_CLIENT=predis` by default, since the phpredis PHP extension isn't available on every machine (predis is a pure-PHP client and needs nothing beyond `composer install`). Switch to `REDIS_CLIENT=phpredis` if you have the extension installed.

Create the database, then migrate and seed (seed data includes the Free/Pro/Team plans and sample content, used by the content listing and subscription endpoints):

```bash
mysql -u root -e "CREATE DATABASE content_pass;"

php artisan migrate --seed
```

Run the API and, in a separate terminal, a queue worker:

```bash
php artisan serve
# in a second terminal:
php artisan horizon      # or `php artisan queue:work` — see Background Jobs
```

The API is now available at `http://localhost:8000`.

### Endpoints

| Method | Endpoint                | Auth required | Role required | Description               |
|--------|--------------------------|:--------------:|:--------------:|----------------------------|
| GET    | `/api/v1/health`         | No             | —               | Health check               |
| POST   | `/api/v1/auth/register`  | No             | —               | Register a new user        |
| POST   | `/api/v1/auth/login`     | No             | —               | Log in, receive a JWT      |
| POST   | `/api/v1/auth/logout`    | Yes            | —               | Invalidate current token   |
| POST   | `/api/v1/auth/refresh`   | Yes            | —               | Refresh the JWT            |
| GET    | `/api/v1/auth/me`        | Yes            | —               | Get the current user       |
| GET    | `/api/v1/content`        | Yes            | —               | Paginated list of published content, with a per-user `accessible` flag |
| GET    | `/api/v1/plans`          | No             | —               | Public list of available plans |
| POST   | `/api/v1/subscriptions`  | Yes            | —               | Subscribe to a plan (mock billing — see [Subscriptions](#subscriptions)) |
| GET    | `/api/v1/subscriptions/current` | Yes     | —               | Get the current active subscription, or `null` |
| DELETE | `/api/v1/subscriptions/current` | Yes     | —               | Cancel the current active subscription |
| GET    | `/api/v1/admin/ping`     | Yes            | `admin`         | Placeholder — proves RBAC works |

### Running Tests

```bash
php artisan test
```

Tests use an in-memory SQLite database (configured in `phpunit.xml`) and the `array` cache/session/mail drivers, so they don't need a real MySQL or Redis connection.

### Code Style

```bash
./vendor/bin/pint
```

## Deployment

Deploy prep for a live demo on [Render](https://render.com)'s free tier lives in `render.yaml` (a [Blueprint](https://render.com/docs/blueprint-spec)). **This is config only — nothing has actually been deployed.** Applying the blueprint, generating secrets, and wiring up Redis are manual steps.

**Postgres, not MySQL:** Render's free managed database is Postgres — it doesn't offer free MySQL. This needed zero application code changes: `config/database.php` already ships a `pgsql` connection (same env var names as `mysql` — `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), so switching is purely `DB_CONNECTION=pgsql` plus connection details. `render.yaml` actually wires this up with a single `DB_URL` (Render's `fromDatabase: connectionString`) instead of five separate vars — Laravel's `ConfigurationUrlParser` parses a connection URL into host/port/database/username/password automatically, and Render's Blueprint spec doesn't expose discrete host/port properties from a database reference anyway, only `connectionString`/`connectionPoolString`/`user`/`password`/`database`. The Dockerfile now installs `pdo_pgsql` alongside the existing `pdo_mysql`, so the same image still works against the local Docker Compose MySQL setup too.

**Redis is external, on purpose.** Render has no free managed Redis, so the plan is [Upstash](https://upstash.com)'s free tier instead. This is *not* in `render.yaml` — a Blueprint can't populate a var from a dashboard-only external provider — so after creating the Upstash database, set these three manually in the Render dashboard: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` (matching the names already used everywhere else in this app). `render.yaml` does set `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, and `REDIS_CLIENT=predis` — those are just driver selection, not secrets, so they're fine to commit.

**Secrets — set manually, never committed:** `APP_KEY` and `JWT_SECRET` are declared in `render.yaml` with `sync: false`, which tells Render to prompt for them during Blueprint setup instead of storing a value. Generate both locally first and paste them in when prompted:

```bash
php artisan key:generate --show
php artisan jwt:secret --show
```

**No `preDeployCommand` — and that's correct, not an oversight.** Render's Blueprint spec has a `preDeployCommand` field for running one-off commands before a new deploy goes live, but it's explicitly *not supported for `runtime: docker` services* (Docker services get `dockerCommand` instead, which just replaces the container's `CMD` — there's no separate pre-deploy phase to hook into). So "run migrations on deploy" is instead handled by `docker/entrypoint.sh` (already built on Day 11, unchanged): it runs `php artisan migrate --seed --force` before starting the server, on every container start — idempotent and safe, and it's what actually satisfies that requirement here.

**Known gap — no free worker service.** Render's free plan only runs one always-on web service; there's no free "Background Worker" for `php artisan horizon`. `render.yaml` still sets `QUEUE_CONNECTION=redis` to match every other environment, but as configured, nothing will actually consume that queue on a free-tier deploy — the one queued job (`SendWelcomeEmailJob`) will sit unprocessed rather than error. This is flagged, not solved: the two real options are paying for a Render worker later, or switching `QUEUE_CONNECTION` to `sync` in the dashboard for the demo (so the welcome email just sends inline, no worker needed). Deciding between them wasn't part of today's config-only scope.

**`APP_URL`:** set to `https://content-pass.onrender.com` as a best-effort default, matching the `name:` chosen for the web service in `render.yaml` — Render assigns `<name>.onrender.com` unless that's taken, in which case it appends a suffix. Confirm this against the actual assigned URL after the first deploy and fix it in the dashboard if it doesn't match — the same value also needs to become this section's [Live Demo](#live-demo) link.

## Implemented

- JWT authentication — register, login, refresh, logout, current-user endpoint
- RBAC middleware — `role:<name>` route middleware backed by a `role` column on `users`
- Database schema — `plans`, `contents`, `subscriptions`, plus `role`/`plan_id` on `users`
- Queued email notifications — a welcome email sent via a Redis-queued job, managed with Laravel Horizon
- Redis-cached content listing — a paginated, plan-aware content endpoint with observer-based cache invalidation
- Mock subscription/billing flow — subscribe/cancel/read current subscription, with plan upgrades modeled as cancel-then-resubscribe (no real payment gateway)
- `docker compose up` local stack — backend, queue worker, MySQL, Redis, and the sibling Next.js frontend, seeded automatically on startup
- Render deployment config (`render.yaml` + a Postgres-compatible Dockerfile) — prepared, not yet deployed; see [Deployment](#deployment)

## Planned

- Actually deploying to Render and filling in the [Live Demo](#live-demo) link/screenshots
- A decision on the free-tier queue-worker gap (see [Deployment](#deployment)) — pay for a worker, or run the queue synchronously for the demo
- Real payment gateway integration (Stripe or similar) in place of the mock billing flow
- Admin endpoints for managing plans and content
- CI pipeline (lint, test, on every push)
- Expanded test coverage
- A production-oriented Docker image (PHP-FPM + Nginx) alongside the current dev-friendly one
