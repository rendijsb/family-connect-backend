#!/bin/bash

echo "=== FAMILY CONNECT DEBUG STARTUP ==="
echo "Timestamp: $(date)"
echo "Working directory: $(pwd)"
echo "User: $(whoami)"
echo "Home: $HOME"

echo "=== PHP INFO ==="
php --version
php -m | grep -E "(pdo|sqlite|pgsql)"

echo "=== LARAVEL INFO ==="
if [ -f "artisan" ]; then
    echo "Artisan file exists"
    php artisan --version || echo "Laravel command failed"
    php artisan config:show app || echo "Config failed"
else
    echo "ERROR: artisan file not found!"
    ls -la
fi

echo "=== FILE PERMISSIONS ==="
ls -la /var/www/html/
ls -la /var/www/html/public/
ls -la /var/www/html/storage/ || echo "No storage directory"

echo "=== APACHE CONFIG TEST ==="
apache2ctl -t || echo "Apache config test failed"

echo "=== APACHE PORTS ==="
cat /etc/apache2/ports.conf
echo "=== APACHE SITE CONFIG ==="
cat /etc/apache2/sites-available/000-default.conf

echo "=== NETWORK TEST ==="
netstat -tulpn || echo "netstat not available"

echo "=== ENVIRONMENT VARIABLES ==="
echo "APP_ENV: $APP_ENV"
echo "APP_DEBUG: $APP_DEBUG"
echo "APP_KEY: ${APP_KEY:0:20}..."
echo "DB_CONNECTION: $DB_CONNECTION"

echo "=== STARTING APACHE IN BACKGROUND ==="
apache2-foreground &
APACHE_PID=$!

echo "Apache PID: $APACHE_PID"
sleep 5

echo "=== TESTING LOCAL CONNECTION ==="
curl -v http://localhost:8080/ping || echo "Local curl test failed"

echo "=== KEEPING APACHE RUNNING ==="
wait $APACHE_PID
