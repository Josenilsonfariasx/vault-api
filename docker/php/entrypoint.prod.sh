#!/bin/bash
set -e

cd /var/www

echo "=== Production Mode ==="

# Corrige permissões do storage e cache PRIMEIRO
echo "Setting permissions..."
mkdir -p /var/www/storage/framework/{sessions,views,cache}
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

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

# Corrige permissões novamente após cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Cria diretório de logs do supervisor
mkdir -p /var/log/supervisor

echo "Application ready! Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
