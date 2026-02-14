#!/bin/sh
set -e

# Read password from Docker secret
REDIS_PASSWORD=$(cat /run/secrets/redis_password)

# Start Redis with password from secret
exec redis-server \
    --appendonly yes \
    --maxmemory 256mb \
    --maxmemory-policy allkeys-lru \
    --requirepass "$REDIS_PASSWORD"
