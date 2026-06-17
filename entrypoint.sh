#!/bin/sh
set -eu

# Wait for DB (compose healthcheck does not gate artisan network timing)
until php artisan db:monitor >/dev/null 2>&1; do
  sleep 2
done

php artisan migrate --force
php artisan optimize:clear
php artisan storage:link --force

# Ensure storage directories exist and have correct permissions
mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache
chmod -R 777 storage bootstrap/cache

exec "$@"
