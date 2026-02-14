#!/bin/sh
set -e

# Load secrets from Docker secrets files into environment variables
if [ -f /run/secrets/redis_password ]; then
    export REDIS_PASSWORD=$(cat /run/secrets/redis_password)
fi

if [ -f /run/secrets/db_password ]; then
    export DB_PASSWORD=$(cat /run/secrets/db_password)
fi

if [ -f /run/secrets/meilisearch_key ]; then
    export MEILISEARCH_KEY=$(cat /run/secrets/meilisearch_key)
fi

# Generate optimized files at runtime with actual environment variables
echo "Caching Laravel configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "Laravel caches generated successfully"

# Execute the main command
exec "$@"
