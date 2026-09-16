#!/bin/sh
set -e

# Wait for Postgres (Neon direct/pooled URL may cold-start).
if [ -n "$DB_URL" ] || [ -n "$DB_HOST" ]; then
  echo "Waiting for database..."
  for i in $(seq 1 30); do
    if php artisan db:show --no-interaction >/dev/null 2>&1; then
      echo "Database reachable."
      break
    fi
    if [ "$i" -eq 30 ]; then
      echo "WARNING: database not reachable after 30s — continuing anyway."
    fi
    sleep 2
  done
fi

# Fresh caches built from the *runtime* environment (never bake these).
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan event:cache --no-interaction || true

# Safe, idempotent schema sync on every deploy.
php artisan migrate --force --no-interaction

exec "$@"
