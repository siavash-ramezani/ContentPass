# ContentPass

ContentPass is a REST API for a subscription-based content platform — creators publish gated content, and subscribers get access based on their plan tier. This is a **portfolio project**, built incrementally in public over a series of days; it is not a production system.

> **Status: Day 7.** Implemented so far: JWT authentication, RBAC middleware, the core database schema, Redis-backed background jobs via Horizon, and a Redis-cached content listing endpoint. See [Implemented](#implemented) / [Planned](#planned) below for the full picture.

## Tech Stack

- **Framework:** Laravel 12 (PHP 8.2+)
- **Auth:** JWT via [tymon/jwt-auth](https://github.com/tymondesigns/jwt-auth)
- **Database:** MySQL
- **Queues:** Redis, managed with [Laravel Horizon](https://laravel.com/docs/horizon)
- **Cache:** Redis
- **Testing:** PHPUnit
- **Code style:** Laravel Pint

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

## Setup

**Prerequisites:** PHP 8.2+, Composer, MySQL running locally, Redis running locally (Docker support for all of this is planned — see below).

```bash
git clone <repo-url> content-pass
cd content-pass

composer install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edit `.env` and set the values below for your local environment (all are already present in `.env.example`, grouped here by what they're for):

| Purpose | Variables |
|---|---|
| App | `APP_NAME`, `APP_URL` |
| Database (MySQL) | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Redis (queue + cache) | `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` |
| JWT | `JWT_SECRET` (generated by `jwt:secret` above), `JWT_TTL` |
| Mail | `MAIL_MAILER` (`log` by default — see [Background Jobs](#background-jobs)) |

> `REDIS_CLIENT=predis` by default, since the phpredis PHP extension isn't available on every machine (predis is a pure-PHP client and needs nothing beyond `composer install`). Switch to `REDIS_CLIENT=phpredis` if you have the extension installed.

Create the database, then migrate and seed (seed data includes the Free/Pro/Team plans and sample content, used by the content listing endpoint):

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

## Implemented

- JWT authentication — register, login, refresh, logout, current-user endpoint
- RBAC middleware — `role:<name>` route middleware backed by a `role` column on `users`
- Database schema — `plans`, `contents`, `subscriptions`, plus `role`/`plan_id` on `users`
- Queued email notifications — a welcome email sent via a Redis-queued job, managed with Laravel Horizon
- Redis-cached content listing — a paginated, plan-aware content endpoint with observer-based cache invalidation

## Planned

- Subscription purchase and management flow (create/cancel, plan upgrades)
- Admin endpoints for managing plans and content
- Docker Compose for local development (app, MySQL, Redis in containers)
- A Next.js frontend
- CI pipeline (lint, test, on every push)
- Expanded test coverage
