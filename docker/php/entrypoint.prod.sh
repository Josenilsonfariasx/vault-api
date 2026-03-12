#!/bin/bash
set -e

cd /var/www

echo "=== Production Mode ==="

# Cache das configurações Laravel
echo "Caching configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations
echo "Running migrations..."
php artisan migrate --force || echo "Migration skipped"

# Cria diretório de logs do supervisor
mkdir -p /var/log/supervisor

echo "Application ready! Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
