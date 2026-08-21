# ContentPass

ContentPass is the backend API for a subscription-based content platform — creators publish gated content, and subscribers pay for tiered access to it. This repository is the Laravel API that will power that platform.

> **Status: Day 5** of a portfolio build. So far: project setup + JWT auth scaffolding (Day 1), database schema for plans/content/subscriptions (Day 2), role-based access control (Day 3), and Redis-backed background jobs via Horizon (Day 5). No subscription/content business logic yet — see [Roadmap](#roadmap).

## Tech Stack

- **Framework:** Laravel 12 (PHP 8.2+)
- **Auth:** JWT via [tymon/jwt-auth](https://github.com/tymondesigns/jwt-auth)
- **Database:** MySQL
- **Queues:** Redis, managed with [Laravel Horizon](https://laravel.com/docs/horizon)
- **Code style:** Laravel Pint

## Project Structure

This is an API-only project — `routes/api.php` is the entry point, and the default Blade/web scaffolding has been stripped out. The `app/` folder is organized to feel domain-driven even at this early stage, so it scales cleanly as subscriptions/plans/content are added in later days:

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/       # Versioned API controllers (AuthController, HealthController, ...)
│   │   └── Concerns/     # Shared controller traits (e.g. consistent JSON responses)
│   ├── Requests/         # Form Request validation classes (grouped by domain, e.g. Auth/)
│   └── Resources/        # API Resource transformers (response shaping)
├── Models/                # Eloquent models
└── Services/              # Business logic layer, kept separate from controllers
```

Why this shape:

- **`Controllers/Api/V1/`** — versioning from day one means a `V2` can be introduced later without breaking existing clients.
- **`Requests/`** — validation rules live outside controllers so controllers stay thin and requests stay testable/reusable.
- **`Resources/`** — response shaping is centralized, so the JSON contract (e.g. hiding internal fields) doesn't leak across controllers.
- **`Services/`** — a home for business logic as the domain grows (subscription logic, billing, content access rules), keeping controllers as thin HTTP adapters rather than where logic accumulates.

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

Role checks are enforced by the `role` route middleware (`App\Http\Middleware\EnsureUserHasRole`, aliased as `role` in `bootstrap/app.php`). It reads the authenticated user's `role` column (`user` or `admin`, from the Day 2 schema) and compares it against the role(s) passed to the middleware.

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

## Setup

**Prerequisites:** PHP 8.2+, Composer, MySQL running locally, Redis running locally (Docker support for all of this comes later).

```bash
git clone <repo-url> content-pass
cd content-pass

composer install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Edit .env: set DB_DATABASE, DB_USERNAME, DB_PASSWORD for your local MySQL,
# and create the database, e.g.:
#   mysql -u root -e "CREATE DATABASE content_pass;"
#
# Also confirm Redis is reachable at REDIS_HOST:REDIS_PORT — it backs the
# queue (QUEUE_CONNECTION=redis) and Horizon's dashboard metrics.

php artisan migrate

php artisan serve
```

The API is now available at `http://localhost:8000`. In a separate terminal, run `php artisan horizon` (or `php artisan queue:work` — see [Background Jobs](#background-jobs)) to process queued jobs like the welcome email.

### Endpoints

| Method | Endpoint                | Auth required | Role required | Description               |
|--------|--------------------------|:--------------:|:--------------:|----------------------------|
| GET    | `/api/v1/health`         | No             | —               | Health check               |
| POST   | `/api/v1/auth/register`  | No             | —               | Register a new user        |
| POST   | `/api/v1/auth/login`     | No             | —               | Log in, receive a JWT      |
| POST   | `/api/v1/auth/logout`    | Yes            | —               | Invalidate current token   |
| POST   | `/api/v1/auth/refresh`   | Yes            | —               | Refresh the JWT            |
| GET    | `/api/v1/auth/me`        | Yes            | —               | Get the current user       |
| GET    | `/api/v1/admin/ping`     | Yes            | `admin`         | Placeholder — proves RBAC works |

Authenticated requests use `Authorization: Bearer <token>`.

### Code Style

```bash
./vendor/bin/pint
```

## Roadmap

- ~~**Day 2:** `role`/`plan_id` schema, `plans`/`contents`/`subscriptions` tables~~ ✅
- ~~**Day 3:** RBAC middleware (`role:admin`)~~ ✅
- ~~**Day 5:** Redis queues, Horizon, welcome-email background job~~ ✅
- **Next up:** Content and subscription business logic (gated content access by plan level, subscription lifecycle), plan/content management endpoints for admins
- **Later:** Redis caching, more background jobs (webhooks, digest emails), Docker-based local environment
