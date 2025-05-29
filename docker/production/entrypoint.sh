#!/bin/bash
set -e

export IN_DOCKER=true

if [ ! -f /var/www/.env ]; then
  echo "No .env file found"
  exit 1
fi

if ! grep -q "APP_KEY=" /var/www/.env; then
  echo "APP_KEY is not set in the environment"
  exit 1
fi

# Create necessary directories if they don't exist
for dir in storage/framework/sessions storage/framework/views storage/framework/cache storage/framework/cache/data storage/app/public storage/logs bootstrap/cache public/uploads; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir" 2>/dev/null || true
    fi
done

# Only try to change ownership if we have write permission to the parent directory
# This avoids "Operation not permitted" errors on mounted volumes in Kubernetes
if [ -w /var/www ] && [ -w /var/www/storage ] && [ -w /var/www/bootstrap ]; then
    echo "Setting ownership for writable directories..."
    for dir in storage bootstrap/cache public/uploads; do
        if [ -d "$dir" ]; then
            chown -R www-data:www-data "$dir" 2>/dev/null || true
        fi
    done
else
    echo "Skipping ownership changes - directories are mounted or not writable"
fi

task setup:prod

# Pass control to the command
exec "$@" 