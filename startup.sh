#!/bin/bash

# Laravel startup script for production deployment
echo "🚀 Starting Laravel application..."

# Set proper permissions for storage and cache
echo "📁 Setting up storage permissions..."
mkdir -p storage/logs
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Clear and cache Laravel configurations
echo "🔧 Setting up Laravel caches..."
php artisan config:clear
php artisan config:cache

php artisan route:clear
php artisan route:cache

php artisan view:clear
php artisan view:cache

# Run database migrations with error handling
echo "📊 Running database migrations..."
if php artisan migrate --force; then
    echo "✅ Database migrations completed successfully"
else
    echo "⚠️ Database migrations had issues, continuing..."
fi

# Clear application cache
echo "🧹 Clearing application cache..."
php artisan cache:clear

# Ensure Redis connection is working
echo "🔗 Testing Redis connection..."
if php artisan tinker --execute="Redis::ping();" 2>/dev/null; then
    echo "✅ Redis connection successful"
else
    echo "⚠️ Redis connection issues, WebSocket may not work properly"
fi

# Start Reverb WebSocket server in the background if in production
if [ "$APP_ENV" = "production" ] && [ "$BROADCAST_DRIVER" = "reverb" ]; then
    echo "🔌 Starting Reverb WebSocket server..."
    # Give it a few seconds to ensure all services are ready
    sleep 3
    nohup php artisan reverb:start --host=0.0.0.0 --port=8080 > /var/www/html/storage/logs/reverb.log 2>&1 &
    echo "🔌 Reverb server started (logs: storage/logs/reverb.log)"
fi

echo "✅ Laravel application startup complete!"