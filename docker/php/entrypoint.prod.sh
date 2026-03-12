#!/bin/bash
set -e

cd /var/www

echo "=== Production Mode ==="

# Publica assets do Swagger UI (caso não existam)
echo "Publishing Swagger assets..."
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider" --force 2>/dev/null || true

# Gera documentação Swagger
echo "Generating Swagger documentation..."
php artisan l5-swagger:generate || echo "Swagger generation skipped"

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
