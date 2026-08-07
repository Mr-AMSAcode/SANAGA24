#!/bin/sh
set -e

# All services (app, queue, reverb, scheduler) share this one image — only
# the main "app" container should run migrations/seeding/caching, so they
# don't race each other on every `docker compose up`.
ROLE="${CONTAINER_ROLE:-app}"

echo "Waiting for the database (${DB_HOST}:${DB_PORT})..."
until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" -q; do
    sleep 2
done
echo "Database is up."

if [ "$ROLE" = "app" ]; then
    php artisan storage:link --force
    php artisan migrate --force

    # Safe to re-run on every deploy: RolesAndPermissionsSeeder uses
    # firstOrCreate and DatabaseSeeder only seeds demo content when
    # app()->isLocal(), which is false here (APP_ENV=production).
    php artisan db:seed --force

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
