#!/bin/sh
set -e

echo "Starting entrypoint script..."

# Ensure storage directories exist immediately
mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache
chmod -R 777 storage bootstrap/cache

# Force clear all caches that might have been copied from host
echo "Clearing caches..."
rm -f bootstrap/cache/*.php
php artisan config:clear || echo "Config clear failed, continuing..."
php artisan cache:clear || echo "Cache clear failed, continuing..."
php artisan view:clear || echo "View clear failed, continuing..."

echo "Checking database connection..."
# Try to monitor DB but don't loop forever, max 10 tries
MAX_TRIES=10
COUNT=0
until php artisan db:monitor >/dev/null 2>&1 || [ $COUNT -eq $MAX_TRIES ]; do
  echo "Waiting for database... ($COUNT/$MAX_TRIES)"
  sleep 3
  COUNT=$((COUNT + 1))
done

echo "Running migrations..."
php artisan migrate --force || echo "Migration failed, continuing..."

echo "Linking storage..."
php artisan storage:link --force || echo "Storage link failed, continuing..."

echo "Fixing permissions again..."
chmod -R 777 storage bootstrap/cache

echo "Entrypoint finished. Starting PHP-FPM..."
exec "$@"
