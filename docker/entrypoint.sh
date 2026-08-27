#!/bin/sh
set -e

cd /var/www/html

# .env is gitignored (and excluded from the image via .dockerignore), so a
# fresh container never has one baked in — create it from .env.example the
# first time this container's filesystem sees it. On a plain `restart`
# (same container, not recreated) this is a no-op since .env already exists.
if [ ! -f .env ]; then
    echo "No .env found — creating one from .env.example"
    cp .env.example .env
fi

# Only (re)generate secrets if they're not already set, so a restart doesn't
# invalidate existing JWTs / encrypted data for no reason.
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

if ! grep -Eq '^JWT_SECRET=.+' .env; then
    php artisan jwt:secret --force --no-interaction
fi

# Safe to run on every start: `migrate` only applies pending migrations,
# and both seeders use updateOrCreate, so re-seeding doesn't duplicate rows.
php artisan migrate --seed --force --no-interaction

exec "$@"
