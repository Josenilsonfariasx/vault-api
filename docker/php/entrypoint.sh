#!/bin/bash
set -e

cd /var/www

# Load APP_ENV from .env if exists
if [ -f .env ]; then
  export $(grep -E '^APP_ENV=' .env | xargs)
fi

APP_ENV=${APP_ENV:-local}
echo "Environment: $APP_ENV"

# Fix permissions
chown -R www:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Dependencies
if [ "$APP_ENV" = "local" ]; then
  if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Installing dependencies..."
    su www -s /bin/bash -c "composer install --no-interaction --prefer-dist --optimize-autoloader"
  else
    echo "Dependencies already installed, skipping..."
  fi
else
  echo "Production mode - skipping composer install"
fi

# Wait for database
echo "Waiting for database (max 30s)..."
for i in $(seq 1 30); do
  if nc -z postgres 5432 2>/dev/null; then
    echo "Database ready!"
    break
  fi
  sleep 1
done

# Migrations
echo "Running migrations..."
su www -s /bin/bash -c "php artisan migrate --force" || echo "Migration skipped"

# Swagger - only generate if not exists or in local
if [ "$APP_ENV" = "local" ]; then
  if [ ! -f "storage/api-docs/api-docs.json" ]; then
    echo "Generating Swagger documentation..."
    su www -s /bin/bash -c "php artisan l5-swagger:generate" || echo "Swagger generation skipped"
  else
    echo "Swagger docs already exist, skipping..."
  fi
else
  echo "Production mode - swagger should be pre-generated"
fi

echo "Application ready!"

exec php-fpm
