# Single-stage image: a portfolio-scope dev/demo stack doesn't need the
# extra Nginx + PHP-FPM split (see README's "Running with Docker" section
# for the reasoning). `php artisan serve` behind a published port is enough.
FROM php:8.2-cli

# System packages + PHP extensions Laravel/JWT/Horizon need:
# - pdo_mysql: talk to the MySQL service (docker-compose / local dev)
# - pdo_pgsql: talk to Postgres (Render's free managed DB — see render.yaml
#   and the README's Deployment section for why this app runs on two
#   different databases depending on where it's hosted)
# - mbstring, bcmath: required by Laravel / the JWT library
# - pcntl, posix: required by Horizon's supervisor process (laravel/horizon
#   declares both as hard requirements). Both are POSIX-only and don't
#   exist on native Windows PHP builds (see the Day 5 note in the README)
#   — but this is a Linux container, so they're available here and
#   `php artisan horizon` runs for real in the `queue` service.
RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        git \
        libzip-dev \
        libpq-dev \
        default-mysql-client \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring bcmath pcntl posix \
    && rm -rf /var/lib/apt/lists/*

# Grab the Composer binary from its official image rather than installing it.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# .env is intentionally NOT copied in (see .dockerignore) — the entrypoint
# creates one from .env.example on first start.
#
# NOTE: this deliberately installs require-dev too (no --no-dev). The
# startup seed (DatabaseSeeder) calls User::factory(), which needs
# fakerphp/faker — a dev dependency. Splitting that out so a leaner
# --no-dev install would work is an application-code change (moving the
# demo-user seed elsewhere, or promoting Faker to a real dependency),
# which is out of scope for a Docker/infra-only day.
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]

# Shell form (not an exec-form array) so $PORT actually expands at runtime.
# docker-compose never sets PORT, so it falls back to 8000 there, matching
# the "8000:8000" mapping in docker-compose.yml; Render sets PORT itself
# and requires the app to bind to whatever value it provides.
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
