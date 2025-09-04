#!/bin/bash

# DigitalOcean App Platform startup script
set -e

echo "=== FAMILY CONNECT STARTUP SCRIPT ==="
echo "Starting at: $(date)"

# Make sure we're in the right directory
cd /workspace

# Debug environment
echo "=== ENVIRONMENT CHECK ==="
echo "APP_ENV: ${APP_ENV:-not-set}"
echo "APP_DEBUG: ${APP_DEBUG:-not-set}"
echo "DATABASE_URL: ${DATABASE_URL:-not-set}" | sed 's/postgres:\/\/[^:]*:[^@]*@/postgres:\/\/***:***@/'
echo "Working directory: $(pwd)"
echo "PHP Version: $(php --version | head -n 1)"

# Set proper permissions
echo "=== SETTING PERMISSIONS ==="
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Clear and cache config for production
echo "=== LARAVEL OPTIMIZATION ==="
php artisan config:clear || echo "Config clear failed"
php artisan config:cache || echo "Config cache failed"
php artisan route:clear || echo "Route clear failed"
php artisan route:cache || echo "Route cache failed"
php artisan view:clear || echo "View clear failed"
php artisan view:cache || echo "View cache failed"

# Check database connection
echo "=== DATABASE CONNECTION TEST ==="
timeout 30 php artisan migrate:status || echo "Database connection failed or migrations needed"

# Generate storage link if it doesn't exist
php artisan storage:link 2>/dev/null || echo "Storage link already exists or failed"

# Test health endpoint
echo "=== TESTING HEALTH ENDPOINT ==="
php artisan serve --host=0.0.0.0 --port=8080 &
SERVER_PID=$!
sleep 5

# Test local health check
curl -f http://localhost:8080/health || echo "Health check failed"

# Kill test server
kill $SERVER_PID 2>/dev/null || true

echo "=== STARTUP COMPLETE ==="
echo "Ready to serve requests on port 8080"

# Start the actual server (this will be handled by the platform)
exec "$@"
