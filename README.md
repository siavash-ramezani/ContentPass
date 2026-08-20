# ContentPass

ContentPass is the backend API for a subscription-based content platform — creators publish gated content, and subscribers pay for tiered access to it. This repository is the Laravel API that will power that platform.

> **Status: Day 3** of a portfolio build. So far: project setup + JWT auth scaffolding (Day 1), database schema for plans/content/subscriptions (Day 2), and role-based access control (Day 3). No subscription/content business logic yet — see [Roadmap](#roadmap).

## Tech Stack

- **Framework:** Laravel 12 (PHP 8.2+)
- **Auth:** JWT via [tymon/jwt-auth](https://github.com/tymondesigns/jwt-auth)
- **Database:** MySQL
- **Cache / Queues (planned):** Redis
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

## Setup

**Prerequisites:** PHP 8.2+, Composer, MySQL running locally (Docker support comes later).

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

php artisan migrate

php artisan serve
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
| GET    | `/api/v1/admin/ping`     | Yes            | `admin`         | Placeholder — proves RBAC works |

Authenticated requests use `Authorization: Bearer <token>`.

### Code Style

```bash
./vendor/bin/pint
```

## Roadmap

- ~~**Day 2:** `role`/`plan_id` schema, `plans`/`contents`/`subscriptions` tables~~ ✅
- ~~**Day 3:** RBAC middleware (`role:admin`)~~ ✅
- **Day 4+:** Content and subscription business logic (gated content access by plan level, subscription lifecycle), plan/content management endpoints for admins
- **Later:** Redis caching, queues for async jobs (emails, webhooks), Docker-based local environment
