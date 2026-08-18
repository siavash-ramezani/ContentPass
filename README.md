# ContentPass

ContentPass is the backend API for a subscription-based content platform — creators publish gated content, and subscribers pay for tiered access to it. This repository is the Laravel API that will power that platform.

> **Status: Day 1** of a portfolio build. This stage covers project setup, folder structure, and JWT auth scaffolding only. No subscriptions, plans, content, or RBAC yet — see [Roadmap](#roadmap).

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

### Endpoints (Day 1)

| Method | Endpoint              | Auth required | Description             |
|--------|------------------------|:--------------:|--------------------------|
| GET    | `/api/v1/health`       | No             | Health check             |
| POST   | `/api/v1/auth/register`| No             | Register a new user      |
| POST   | `/api/v1/auth/login`   | No             | Log in, receive a JWT    |
| POST   | `/api/v1/auth/logout`  | Yes            | Invalidate current token |
| POST   | `/api/v1/auth/refresh` | Yes            | Refresh the JWT          |
| GET    | `/api/v1/auth/me`      | Yes            | Get the current user     |

Authenticated requests use `Authorization: Bearer <token>`.

### Code Style

```bash
./vendor/bin/pint
```

## Roadmap

- **Day 2:** `role` and `plan_id` on the User model, RBAC (roles/permissions), plan/subscription schema
- **Day 3+:** Subscriptions and billing logic, gated content endpoints
- **Later:** Redis caching, queues for async jobs (emails, webhooks), Docker-based local environment
