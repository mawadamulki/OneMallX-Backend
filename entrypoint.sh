#!/bin/sh
set -eu

# Wait for DB
until php artisan db:monitor >/dev/null 2>&1; do
  sleep 2
done

# Force clear all caches that might have been copied from host
rm -f bootstrap/cache/*.php
php artisan config:clear
php artisan cache:clear
php artisan view:clear

php artisan migrate --force
php artisan storage:link --force

# Ensure storage directories exist and have correct permissions
mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache
chmod -R 777 storage bootstrap/cache

exec "$@"
